#!/usr/bin/env python3
"""
obstack Agent — Collecteur de métriques système
Exécuté toutes les 60 secondes par le timer systemd.

Usage: python3 collect_metrics.py --api-url URL --token TOKEN [--output FILE]
"""

import json
import time
import subprocess
import os
import sys
import socket
import argparse
from datetime import datetime
from pathlib import Path

try:
    import psutil
except ImportError:
    subprocess.run([sys.executable, "-m", "pip", "install", "psutil", "--quiet",
                    "--break-system-packages"], capture_output=True)
    import psutil


def run(cmd: str, timeout: int = 5) -> str:
    try:
        return subprocess.check_output(
            cmd, shell=True, stderr=subprocess.DEVNULL, timeout=timeout
        ).decode().strip()
    except Exception:
        return ""


def collect_cpu() -> dict:
    """CPU global et par coeur."""
    return {
        "percent":          psutil.cpu_percent(interval=1),
        "per_core":         psutil.cpu_percent(interval=0, percpu=True),
        "count_logical":    psutil.cpu_count(logical=True),
        "count_physical":   psutil.cpu_count(logical=False),
        "freq_mhz":         psutil.cpu_freq().current if psutil.cpu_freq() else None,
        "load_avg_1m":      psutil.getloadavg()[0],
        "load_avg_5m":      psutil.getloadavg()[1],
        "load_avg_15m":     psutil.getloadavg()[2],
        "context_switches": psutil.cpu_stats().ctx_switches,
        "interrupts":       psutil.cpu_stats().interrupts,
    }


def collect_memory() -> dict:
    """Mémoire vive et swap."""
    mem  = psutil.virtual_memory()
    swap = psutil.swap_memory()
    return {
        "total_mb":         mem.total // 1024 // 1024,
        "available_mb":     mem.available // 1024 // 1024,
        "used_mb":          mem.used // 1024 // 1024,
        "percent":          mem.percent,
        "buffers_mb":       getattr(mem, 'buffers', 0) // 1024 // 1024,
        "cached_mb":        getattr(mem, 'cached', 0) // 1024 // 1024,
        "swap_total_mb":    swap.total // 1024 // 1024,
        "swap_used_mb":     swap.used // 1024 // 1024,
        "swap_percent":     swap.percent,
    }


def collect_disks() -> list:
    """Statistiques détaillées par volume."""
    disks = []
    io_counters = psutil.disk_io_counters(perdisk=True)

    for part in psutil.disk_partitions():
        # Ignorer les systèmes de fichiers virtuels
        if part.fstype not in ('ext2', 'ext3', 'ext4', 'xfs', 'btrfs', 'zfs',
                                'nfs', 'nfs4', 'vfat', 'ntfs', 'apfs'):
            continue
        try:
            usage = psutil.disk_usage(part.mountpoint)
        except (PermissionError, OSError):
            continue

        dev_name = part.device.replace('/dev/', '')
        io = io_counters.get(dev_name, None)

        disks.append({
            "device":        part.device,
            "mountpoint":    part.mountpoint,
            "fstype":        part.fstype,
            "total_gb":      round(usage.total / 1e9, 2),
            "used_gb":       round(usage.used / 1e9, 2),
            "free_gb":       round(usage.free / 1e9, 2),
            "used_percent":  usage.percent,
            "read_bytes":    io.read_bytes  if io else None,
            "write_bytes":   io.write_bytes if io else None,
            "read_count":    io.read_count  if io else None,
            "write_count":   io.write_count if io else None,
        })
    return disks


def collect_network_latency() -> dict:
    """Latences réseau vers différentes cibles."""
    latencies = {}

    targets = {
        "loopback":  "127.0.0.1",
        "internet":  "8.8.8.8",
        "cloudflare":"1.1.1.1",
    }

    for label, host in targets.items():
        try:
            start = time.monotonic()
            subprocess.run(
                ["ping", "-c", "1", "-W", "2", host],
                capture_output=True, timeout=3
            )
            latencies[label + "_ms"] = round((time.monotonic() - start) * 1000, 1)
        except Exception:
            latencies[label + "_ms"] = None

    # DNS
    try:
        start = time.monotonic()
        socket.getaddrinfo("google.com", 80)
        latencies["dns_ms"] = round((time.monotonic() - start) * 1000, 1)
    except Exception:
        latencies["dns_ms"] = None

    # Latence TCP vers ports locaux (BDD, Tomcat)
    for label, port in [("tomcat_ms", 8080), ("oracle_ms", 1521),
                         ("postgresql_ms", 5432), ("mysql_ms", 3306),
                         ("redis_ms", 6379)]:
        try:
            start = time.monotonic()
            with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
                s.settimeout(1)
                if s.connect_ex(("127.0.0.1", port)) == 0:
                    latencies[label] = round((time.monotonic() - start) * 1000, 1)
                else:
                    latencies[label] = None
        except Exception:
            latencies[label] = None

    return latencies


def collect_network_io() -> dict:
    """I/O réseau global."""
    net = psutil.net_io_counters()
    return {
        "bytes_sent":    net.bytes_sent,
        "bytes_recv":    net.bytes_recv,
        "packets_sent":  net.packets_sent,
        "packets_recv":  net.packets_recv,
        "errors_in":     net.errin,
        "errors_out":    net.errout,
        "drop_in":       net.dropin,
        "drop_out":      net.dropout,
    }


def collect_processes() -> dict:
    """Informations sur les processus."""
    procs = psutil.pids()

    # Top 5 processus par CPU
    top_cpu = []
    try:
        proc_list = []
        for p in psutil.process_iter(['pid', 'name', 'cpu_percent', 'memory_percent']):
            try:
                proc_list.append(p.info)
            except (psutil.NoSuchProcess, psutil.AccessDenied):
                pass
        top_cpu = sorted(proc_list, key=lambda x: x.get('cpu_percent', 0), reverse=True)[:5]
    except Exception:
        pass

    return {
        "total":        len(procs),
        "running":      len([p for p in psutil.process_iter(['status']) if p.info.get('status') == psutil.STATUS_RUNNING]),
        "sleeping":     len([p for p in psutil.process_iter(['status']) if p.info.get('status') == psutil.STATUS_SLEEPING]),
        "top_cpu":      top_cpu,
        "open_fds":     int(run("lsof 2>/dev/null | wc -l") or 0),
        "connections":  len(psutil.net_connections()),
    }


def collect_services(config: dict) -> dict:
    """Statut des services applicatifs."""
    tomcat_svc = config.get("tomcat_service", "tomcat9")
    db_svc     = config.get("db_service", "oracle")

    def svc_status(name):
        out = run(f"systemctl is-active {name} 2>/dev/null")
        return "running" if out == "active" else "stopped"

    return {
        "tomcat": svc_status(tomcat_svc),
        "db":     svc_status(db_svc),
        "nginx":  svc_status("nginx"),
        "sshd":   svc_status("sshd"),
    }


def collect_system() -> dict:
    """Informations système générales."""
    return {
        "hostname":       socket.getfqdn(),
        "uptime_seconds": int(time.time() - psutil.boot_time()),
        "boot_time":      datetime.fromtimestamp(psutil.boot_time()).isoformat(),
        "users_logged_in": len(psutil.users()),
    }


def collect_all(config: dict) -> dict:
    """Collecte toutes les métriques."""
    ts = time.time()

    return {
        "timestamp":    int(ts),
        "collected_at": datetime.fromtimestamp(ts).isoformat(),
        "system":       collect_system(),
        "cpu":          collect_cpu(),
        "memory":       collect_memory(),
        "disks":        collect_disks(),
        "network_io":   collect_network_io(),
        "latencies":    collect_network_latency(),
        "processes":    collect_processes(),
        "services":     collect_services(config),
        # Alias pour compatibilité avec le format attendu par l'API
        "cpu_percent":       psutil.cpu_percent(),
        "load_average":      list(psutil.getloadavg()),
        "uptime_seconds":    int(time.time() - psutil.boot_time()),
        "process_count":     len(psutil.pids()),
        "connections":       len(psutil.net_connections()),
    }


def send_to_api(metrics: dict, api_url: str, token: str) -> bool:
    """Envoie les métriques à l'API obstack."""
    try:
        import urllib.request
        import urllib.error

        data     = json.dumps(metrics).encode('utf-8')
        request  = urllib.request.Request(
            f"{api_url.rstrip('/')}/agent/metrics",
            data=data,
            headers={
                "Authorization": f"Bearer {token}",
                "Content-Type":  "application/json",
                "User-Agent":    "obstack-Agent/2.1.0",
            },
            method="POST",
        )

        with urllib.request.urlopen(request, timeout=10) as resp:
            return resp.status in (200, 201)

    except Exception as e:
        print(f"[WARN] Envoi métriques échoué: {e}", file=sys.stderr)
        return False


def main():
    parser = argparse.ArgumentParser(description="obstack Metrics Collector")
    parser.add_argument("--api-url",  default=os.getenv("OBS_API_URL", ""),  help="URL API obstack")
    parser.add_argument("--token",    default=os.getenv("OBS_TOKEN", ""),    help="Token agent")
    parser.add_argument("--config",   default="/opt/obstack-agent/config/agent.json", help="Fichier config agent")
    parser.add_argument("--output",   help="Écrire JSON dans un fichier (debug)")
    parser.add_argument("--no-send",  action="store_true", help="Ne pas envoyer à l'API")
    parser.add_argument("--pretty",   action="store_true", help="Pretty-print JSON")
    args = parser.parse_args()

    # Charger la config de l'agent
    config = {}
    if os.path.exists(args.config):
        with open(args.config) as f:
            config = json.load(f)

    api_url = args.api_url or config.get("api_url", "")
    token   = args.token   or config.get("token", "")

    # Collecter les métriques
    metrics = collect_all(config)

    # Output debug
    if args.output:
        indent = 2 if args.pretty else None
        Path(args.output).write_text(json.dumps(metrics, indent=indent))

    # Envoi à l'API
    if not args.no_send and api_url and token:
        success = send_to_api(metrics, api_url, token)
        if not success:
            sys.exit(1)
    elif args.no_send or not api_url:
        print(json.dumps(metrics, indent=2 if args.pretty else None))


if __name__ == "__main__":
    main()
