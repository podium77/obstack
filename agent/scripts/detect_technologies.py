#!/usr/bin/env python3
"""
obstack Agent — Détecteur de technologies
Exécuté lors de l'installation et périodiquement pour mettre à jour l'inventaire.

Usage: python3 detect_technologies.py [--json] [--output /path/to/output.json]
"""

import json
import subprocess
import os
import sys
import re
import socket
import platform
import argparse
from pathlib import Path
from datetime import datetime


def run(cmd: str, timeout: int = 5) -> str:
    """Exécute une commande shell et retourne la sortie."""
    try:
        result = subprocess.run(
            cmd, shell=True, capture_output=True,
            text=True, timeout=timeout
        )
        return result.stdout.strip()
    except Exception:
        return ""


def check_port_listening(port: int) -> bool:
    """Vérifie si un port TCP est en écoute."""
    try:
        with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as s:
            s.settimeout(1)
            return s.connect_ex(('127.0.0.1', port)) == 0
    except Exception:
        return False


def service_active(name: str) -> bool:
    """Vérifie si un service systemd est actif."""
    return run(f"systemctl is-active {name} 2>/dev/null") == "active"


def get_version(cmd: str) -> str | None:
    """Extrait une version depuis la sortie d'une commande."""
    out = run(cmd)
    if not out:
        return None
    match = re.search(r'(\d+\.\d+[\.\d]*)', out)
    return match.group(1) if match else out[:30]


def detect_machine_type() -> dict:
    """Détecte le type de machine (physique, VM, cloud)."""
    machine_type = "unknown"
    hypervisor = ""
    vm_uuid = ""
    manufacturer = ""
    product = ""
    serial = ""

    # systemd-detect-virt (méthode la plus fiable)
    virt = run("systemd-detect-virt 2>/dev/null")
    virt_map = {
        "none":      "physical",
        "kvm":       "vm_kvm",
        "qemu":      "vm_kvm",
        "vmware":    "vm_vmware",
        "microsoft": "vm_hyperv",
        "xen":       "vm_xenpv",
        "docker":    "container",
        "lxc":       "container",
        "lxc-libvirt": "container",
        "podman":    "container",
    }
    machine_type = virt_map.get(virt, "unknown")
    if virt not in ("none", ""):
        hypervisor = virt.upper()

    # DMI/SMBIOS pour infos matérielles
    for field, dest in [
        ("system-manufacturer", "manufacturer"),
        ("system-product-name", "product"),
        ("system-serial-number", "serial"),
        ("system-uuid", "vm_uuid"),
    ]:
        val = run(f"dmidecode -s {field} 2>/dev/null")
        if field == "system-manufacturer": manufacturer = val
        elif field == "system-product-name": product = val
        elif field == "system-serial-number": serial = val
        elif field == "system-uuid": vm_uuid = val

    # Détection cloud via métadonnées
    if run("curl -sf --max-time 1 http://169.254.169.254/latest/meta-data/instance-id 2>/dev/null"):
        machine_type = "cloud_aws"
        hypervisor = "AWS EC2"
    elif run("curl -sf --max-time 1 http://metadata.google.internal/computeMetadata/v1/ -H 'Metadata-Flavor: Google' 2>/dev/null"):
        machine_type = "cloud_gcp"
        hypervisor = "Google Cloud"
    elif os.path.exists("/var/lib/waagent"):
        machine_type = "cloud_azure"
        hypervisor = "Microsoft Azure"

    # Conteneur Docker
    if os.path.exists("/.dockerenv"):
        machine_type = "container"
        hypervisor = "Docker"

    return {
        "machine_type": machine_type,
        "hypervisor":   hypervisor,
        "vm_uuid":      vm_uuid,
        "manufacturer": manufacturer,
        "product":      product,
        "serial":       serial,
    }


def detect_hardware() -> dict:
    """Collecte les informations matérielles."""
    cpu_model  = run("grep -m1 'model name' /proc/cpuinfo | cut -d: -f2 | xargs")
    cpu_cores  = run("nproc --all 2>/dev/null") or "0"
    cpu_threads= run("grep -c '^processor' /proc/cpuinfo 2>/dev/null") or "0"
    ram_kb     = run("grep MemTotal /proc/meminfo | awk '{print $2}'") or "0"
    ram_gb     = round(int(ram_kb) / 1024 / 1024, 1)

    # Disques
    disks = []
    lsblk_out = run("lsblk -J -o NAME,SIZE,TYPE,ROTA,TRAN,MODEL 2>/dev/null")
    if lsblk_out:
        try:
            lsblk_data = json.loads(lsblk_out)
            for dev in lsblk_data.get("blockdevices", []):
                if dev.get("type") == "disk":
                    rota = dev.get("rota", "1")
                    tran  = dev.get("tran", "")
                    if "nvme" in str(tran): dtype = "NVMe"
                    elif rota == "0":       dtype = "SSD"
                    else:                   dtype = "HDD"
                    disks.append({
                        "name":      dev["name"],
                        "size":      dev.get("size", ""),
                        "type":      dtype,
                        "model":     dev.get("model", ""),
                        "transport": tran,
                    })
        except Exception:
            pass

    # Interfaces réseau
    net_ifaces = []
    ip_out = run("ip -j link show 2>/dev/null")
    if ip_out:
        try:
            for iface in json.loads(ip_out):
                if iface.get("link_type") != "loopback":
                    net_ifaces.append({
                        "name": iface["ifname"],
                        "mac":  iface.get("address", ""),
                    })
        except Exception:
            pass

    # Infos OS
    os_info = {}
    if os.path.exists("/etc/os-release"):
        with open("/etc/os-release") as f:
            for line in f:
                if "=" in line:
                    k, v = line.strip().split("=", 1)
                    os_info[k] = v.strip('"')

    return {
        "hostname":          socket.getfqdn(),
        "hostname_short":    socket.gethostname(),
        "kernel_version":    platform.release(),
        "architecture":      platform.machine(),
        "os_id":             os_info.get("ID", ""),
        "os_version":        os_info.get("VERSION_ID", ""),
        "os_pretty":         os_info.get("PRETTY_NAME", ""),
        "timezone":          run("timedatectl show -p Timezone --value 2>/dev/null") or run("cat /etc/timezone 2>/dev/null"),
        "cpu_model":         cpu_model,
        "cpu_cores":         int(cpu_cores),
        "cpu_threads":       int(cpu_threads),
        "ram_gb":            ram_gb,
        "disks":             disks,
        "network_interfaces": net_ifaces,
    }


def detect_kubernetes() -> dict:
    """Détecte si le serveur est un node Kubernetes."""
    is_k8s    = False
    k8s_role  = "worker"
    k8s_version = ""
    runtime   = ""
    node_name = ""

    # Détection via fichiers Kubernetes
    if os.path.exists("/etc/kubernetes"):
        is_k8s = True
        # Master = présence du manifeste API server
        if os.path.exists("/etc/kubernetes/manifests/kube-apiserver.yaml"):
            k8s_role = "master"

    # kubectl disponible?
    kubeconfig_paths = [
        "/etc/kubernetes/admin.conf",
        "/root/.kube/config",
        f"/home/{os.getenv('SUDO_USER', '')}/.kube/config",
    ]
    kubeconfig = next((p for p in kubeconfig_paths if os.path.exists(p)), None)

    if kubeconfig:
        is_k8s = True
        env_kube = f"KUBECONFIG={kubeconfig}"
        k8s_version = run(f"{env_kube} kubectl version --client -o json 2>/dev/null | python3 -c \"import sys,json; d=json.load(sys.stdin); print(d.get('clientVersion',{{}}).get('gitVersion',''))\" 2>/dev/null")
        node_name   = run(f"{env_kube} kubectl get nodes --no-headers -o custom-columns=NAME:.metadata.name 2>/dev/null | head -1")
        runtime     = run(f"{env_kube} kubectl get node {node_name} -o jsonpath='{{.status.nodeInfo.containerRuntimeVersion}}' 2>/dev/null") if node_name else ""

    # containerd / kubelet en cours d'exécution?
    if not is_k8s:
        if service_active("kubelet") or run("pgrep kubelet"):
            is_k8s = True

    return {
        "is_k8s_node":       is_k8s,
        "k8s_role":          k8s_role,
        "k8s_version":       k8s_version,
        "k8s_runtime":       runtime,
        "k8s_node_name":     node_name,
    }


def detect_technologies() -> list:
    """Détecte toutes les technologies installées sur le serveur."""
    techs = []

    # ── Java & Serveurs d'application ──────────────────────────────────
    java_ver = get_version("java -version 2>&1 | head -1")
    if java_ver:
        techs.append({"type": "java", "version": java_ver, "category": "runtime"})

    # Tomcat
    for svc in ["tomcat10", "tomcat9", "tomcat8", "tomcat"]:
        if service_active(svc) or run(f"find /opt/tomcat* /usr/share/tomcat* -name 'catalina.sh' 2>/dev/null | head -1"):
            ver = get_version("find /opt/tomcat /usr/share/tomcat* -name 'RELEASE-NOTES' 2>/dev/null | head -1 | xargs grep -oP 'Apache Tomcat/\\K[^\\s]+' 2>/dev/null")
            port = run("ss -tlnp | grep java | grep -oP ':\\K[0-9]+' | head -1") or "8080"
            status = "running" if service_active(svc) else "installed"
            techs.append({"type": "tomcat", "version": ver, "service": svc, "port": port, "status": status})
            break

    # WildFly / JBoss
    for svc, typ in [("wildfly", "wildfly"), ("jboss-eap", "jboss")]:
        if service_active(svc) or run(f"which {svc} 2>/dev/null"):
            techs.append({"type": typ, "service": svc, "status": "running" if service_active(svc) else "installed"})

    # ── Serveurs Web ───────────────────────────────────────────────────
    if run("which nginx 2>/dev/null"):
        ver = get_version("nginx -v 2>&1")
        techs.append({"type": "nginx", "version": ver, "port": "80/443",
                      "status": "running" if service_active("nginx") else "installed"})

    for svc in ["apache2", "httpd"]:
        if service_active(svc) or run(f"which {svc} 2>/dev/null"):
            ver = get_version(f"{svc} -v 2>&1 | head -1")
            techs.append({"type": "apache", "version": ver, "service": svc, "port": "80/443",
                          "status": "running" if service_active(svc) else "installed"})
            break

    if run("which haproxy 2>/dev/null"):
        ver = get_version("haproxy -v 2>&1 | head -1")
        techs.append({"type": "haproxy", "version": ver,
                      "status": "running" if service_active("haproxy") else "installed"})

    if run("which traefik 2>/dev/null") or check_port_listening(8080) and "traefik" in run("ss -tlnp | grep 8080"):
        ver = get_version("traefik version 2>/dev/null")
        techs.append({"type": "traefik", "version": ver, "status": "running" if service_active("traefik") else "installed"})

    # ── Bases de données ───────────────────────────────────────────────
    # Oracle
    oracle_home = run("find /opt/oracle /u01 /home/oracle -name 'sqlplus' 2>/dev/null | head -1")
    if oracle_home:
        oracle_dir = os.path.dirname(oracle_home)
        ver = get_version(f"{oracle_dir}/sqlplus -v 2>/dev/null")
        sid = run("ps aux | grep pmon | grep -v grep | awk '{print $NF}' | sed 's/ora_pmon_//'")
        techs.append({"type": "oracle", "version": ver, "port": "1521",
                      "oracle_sid": sid,
                      "oracle_home": run("dirname $(dirname $(find /opt/oracle -name 'sqlplus' 2>/dev/null | head -1)) 2>/dev/null"),
                      "status": "running" if run("ps aux | grep -c pmon") != "0" else "installed"})

    # PostgreSQL
    if run("which psql 2>/dev/null"):
        ver = get_version("psql --version 2>&1")
        port = run("pg_lsclusters 2>/dev/null | awk 'NR>1{print $3}' | head -1") or "5432"
        status = "running" if any(service_active(s) for s in ["postgresql", "postgresql@14-main", "postgresql@15-main", "postgresql@16-main"]) else "installed"
        techs.append({"type": "postgresql", "version": ver, "port": port, "status": status})

    # MySQL / MariaDB
    if run("which mysql 2>/dev/null"):
        ver_str = run("mysql --version 2>&1")
        db_type = "mariadb" if "MariaDB" in ver_str else "mysql"
        ver     = get_version(f"mysql --version 2>&1")
        svc     = db_type if db_type == "mariadb" else "mysql"
        techs.append({"type": db_type, "version": ver, "port": "3306",
                      "status": "running" if service_active(svc) else "installed"})

    # MongoDB
    if run("which mongod 2>/dev/null"):
        ver = get_version("mongod --version 2>&1 | head -1")
        techs.append({"type": "mongodb", "version": ver, "port": "27017",
                      "status": "running" if service_active("mongod") else "installed"})

    # Redis
    if run("which redis-server 2>/dev/null"):
        ver = get_version("redis-server --version 2>&1")
        techs.append({"type": "redis", "version": ver, "port": "6379",
                      "status": "running" if service_active("redis") or service_active("redis-server") else "installed"})

    # Elasticsearch
    if check_port_listening(9200) or run("which elasticsearch 2>/dev/null"):
        ver = None
        es_info = run("curl -sf http://localhost:9200 2>/dev/null")
        if es_info:
            try:
                ver = json.loads(es_info).get("version", {}).get("number")
            except Exception:
                pass
        techs.append({"type": "elasticsearch", "version": ver, "port": "9200",
                      "status": "running" if check_port_listening(9200) else "installed"})

    # Cassandra
    if run("which cassandra 2>/dev/null") or check_port_listening(9042):
        ver = get_version("cassandra -v 2>/dev/null")
        techs.append({"type": "cassandra", "version": ver, "port": "9042",
                      "status": "running" if check_port_listening(9042) else "installed"})

    # InfluxDB
    if run("which influxd 2>/dev/null") or check_port_listening(8086):
        ver = get_version("influxd version 2>/dev/null")
        techs.append({"type": "influxdb", "version": ver, "port": "8086",
                      "status": "running" if check_port_listening(8086) else "installed"})

    # ── Runtimes ───────────────────────────────────────────────────────
    if run("which node 2>/dev/null"):
        ver = get_version("node --version 2>/dev/null")
        techs.append({"type": "nodejs", "version": ver, "category": "runtime"})

    if run("which python3 2>/dev/null"):
        ver = get_version("python3 --version 2>/dev/null")
        techs.append({"type": "python", "version": ver, "category": "runtime"})

    if run("which php 2>/dev/null"):
        ver = get_version("php --version 2>/dev/null | head -1")
        fpm_svc = next((s for s in [f"php{v}-fpm" for v in ["8.3","8.2","8.1","8.0"]] + ["php-fpm"] if service_active(s)), None)
        if fpm_svc:
            techs.append({"type": "php_fpm", "version": ver, "service": fpm_svc, "status": "running"})

    if run("which dotnet 2>/dev/null"):
        ver = get_version("dotnet --version 2>/dev/null")
        techs.append({"type": "dotnet", "version": ver, "category": "runtime"})

    # ── Message Brokers ────────────────────────────────────────────────
    if service_active("kafka") or run("which kafka-server-start.sh 2>/dev/null") or check_port_listening(9092):
        techs.append({"type": "kafka", "port": "9092",
                      "status": "running" if service_active("kafka") else "installed"})

    if run("which rabbitmq-server 2>/dev/null") or service_active("rabbitmq-server"):
        ver = get_version("rabbitmqctl version 2>/dev/null")
        techs.append({"type": "rabbitmq", "version": ver, "port": "5672",
                      "status": "running" if service_active("rabbitmq-server") else "installed"})

    if service_active("activemq") or check_port_listening(61616):
        techs.append({"type": "activemq", "port": "61616",
                      "status": "running" if service_active("activemq") else "installed"})

    # ── Conteneurisation ───────────────────────────────────────────────
    if run("which docker 2>/dev/null"):
        ver = run("docker version --format '{{.Server.Version}}' 2>/dev/null")
        containers_raw = run("docker ps --format '{{.Names}}' 2>/dev/null")
        containers = [c for c in containers_raw.split("\n") if c] if containers_raw else []
        techs.append({"type": "docker", "version": ver,
                      "running_containers": len(containers),
                      "containers": containers[:20]})

    if run("which kubectl 2>/dev/null") or os.path.exists("/etc/kubernetes"):
        ver = get_version("kubectl version --client -o json 2>/dev/null | python3 -c \"import sys,json; print(json.load(sys.stdin).get('clientVersion',{}).get('gitVersion',''))\" 2>/dev/null")
        role = "master" if os.path.exists("/etc/kubernetes/manifests/kube-apiserver.yaml") else "worker"
        techs.append({"type": "kubernetes", "version": ver, "node_role": role})

    if run("which containerd 2>/dev/null"):
        ver = get_version("containerd --version 2>/dev/null")
        techs.append({"type": "containerd", "version": ver,
                      "status": "running" if service_active("containerd") else "installed"})

    # ── Monitoring & CI/CD ─────────────────────────────────────────────
    if check_port_listening(9090) or service_active("prometheus"):
        ver = get_version("prometheus --version 2>/dev/null | head -1")
        techs.append({"type": "prometheus", "version": ver, "port": "9090", "status": "running"})

    if check_port_listening(3000) or service_active("grafana-server"):
        techs.append({"type": "grafana", "port": "3000", "status": "running"})

    if service_active("jenkins") or check_port_listening(8080) and "jenkins" in run("ss -tlnp | grep 8080"):
        techs.append({"type": "jenkins", "port": "8080", "status": "running"})

    return techs


def main():
    parser = argparse.ArgumentParser(description="obstack Technology Detector")
    parser.add_argument("--json", action="store_true", help="Output JSON only")
    parser.add_argument("--output", help="Write JSON to file")
    parser.add_argument("--pretty", action="store_true", help="Pretty-print JSON")
    args = parser.parse_args()

    result = {
        "detected_at":    datetime.now().isoformat(),
        "agent_version":  "2.1.0",
        "hardware":       detect_hardware(),
        "machine":        detect_machine_type(),
        "kubernetes":     detect_kubernetes(),
        "technologies":   detect_technologies(),
    }

    indent = 2 if args.pretty or args.output else None
    output = json.dumps(result, indent=indent, ensure_ascii=False)

    if args.output:
        Path(args.output).write_text(output)
        if not args.json:
            print(f"Résultat écrit dans: {args.output}")
            print(f"Technologies détectées: {len(result['technologies'])}")
            print(f"Type machine: {result['machine']['machine_type']}")
            print(f"K8s node: {result['kubernetes']['is_k8s_node']} ({result['kubernetes']['k8s_role']})")
    else:
        print(output)


if __name__ == "__main__":
    main()
