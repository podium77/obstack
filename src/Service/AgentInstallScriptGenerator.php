<?php
namespace App\Service;

use App\Entity\AgentToken;
use App\Entity\Environment;
use App\Entity\Company;

/**
 * Génère le script bash d'installation de l'agent obstack.
 *
 * Le script:
 *  1. Détecte le type de machine (VM/physique/cloud)
 *  2. Détecte toutes les technologies installées
 *  3. Configure l'agent avec le token spécifique à company+env+user
 *  4. Installe un service systemd pour l'agent
 *  5. Lance la première collecte et s'enregistre auprès de la plateforme
 */
class AgentInstallScriptGenerator
{
    public function __construct(
        private readonly string $platformBaseUrl,
        private readonly string $platformApiVersion = 'v1',
        private readonly string $agentArtifactBaseUrl = '',
        private readonly string $agentPubkeyUrl = '',
        private readonly string $agentPubkeyFingerprint = '',
    ) {}

    public function generateForToken(AgentToken $token): string
    {
        return $this->generate(
            $token,
            $token->getEnvironment(),
            $token->getEnvironment()->getCompany(),
        );
    }

    public function generate(AgentToken $token, Environment $env, Company $company): string
    {
        $tokenValue    = $token->getToken();
        $apiUrl        = rtrim($this->platformBaseUrl, '/') . "/api/{$this->platformApiVersion}";
        $companySlug   = $company->getSlug();
        $envSlug       = $env->getSlug();
        $envType       = $env->getType()->value;
        $companyName   = addslashes($company->getName());
        $envName       = addslashes($env->getName());
        $modules       = $token->getModules();
        $modulesList   = implode(',', $modules);
        $modulesLabel  = $modules ? addslashes(implode(', ', $modules)) : 'aucun';
        $modulesJson   = json_encode($modules, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($this->agentPubkeyUrl && $this->agentPubkeyFingerprint) {
            $this->validatePublicKeyUrl($this->agentPubkeyUrl, $this->agentPubkeyFingerprint);
        }

        $script = <<<BASH
#!/usr/bin/env bash
# =============================================================================
# obstack Agent — Script d'installation
# Entreprise : {$companyName}
# Environnement : {$envName} ({$envType})
# Modules activés : {$modulesLabel}
# Généré le : {$this->now()}
# Token : {$token->getMaskedToken()}
# =============================================================================
# Usage: curl -fsSL <url>/agent/install/{$tokenValue} | sudo bash
#        curl -fsSL <url>/agent/install/{$tokenValue} | bash -- --install-dir /home/user/obsagent
# =============================================================================
set -euo pipefail

# ===== CONFIGURATION =====
OBS_TOKEN="{$tokenValue}"
OBS_API_URL="{$apiUrl}"
OBS_COMPANY="{$companySlug}"
OBS_ENV="{$envSlug}"
OBS_AGENT_DIR="/opt/obstack-agent"
OBS_LOG="/var/log/obstack-agent.log"
OBS_USER="obstack-agent"
OBS_VERSION="2.1.0"
OBS_MODULES="{$modulesList}"
OBS_MODULES_JSON='{$modulesJson}'
OBS_ARTIFACT_URL="{$this->agentArtifactBaseUrl}"
OBS_SIG_URL="{$this->agentArtifactBaseUrl}/obsagent.tar.gz.asc"
OBS_PUBKEY_URL="{$this->agentPubkeyUrl}"
OBS_PUBKEY_FILE=""
OBS_PUBKEY_FINGERPRINT="{$this->agentPubkeyFingerprint}"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --install-dir) OBS_AGENT_DIR="$2"; shift 2;;
        --user) OBS_USER="$2"; shift 2;;
        --artifact-url) OBS_ARTIFACT_URL="$2"; shift 2;;
        --sig-url) OBS_SIG_URL="$2"; shift 2;;
        --pubkey-url) OBS_PUBKEY_URL="$2"; shift 2;;
        --pubkey-file) OBS_PUBKEY_FILE="$2"; shift 2;;
        --pubkey-fingerprint) OBS_PUBKEY_FINGERPRINT="$2"; shift 2;;
        --help)
            cat <<'HELP'
Usage: install.sh [--install-dir <path>] [--user <user>] [--artifact-url <url>] [--sig-url <url>] [--pubkey-url <url>] [--pubkey-file <path>] [--pubkey-fingerprint <fingerprint>]
HELP
            exit 0
            ;;
        *) error "Argument inconnu: $1";;
    esac
done

# ===== COULEURS =====
RED='\\033[0;31m'; GREEN='\\033[0;32m'; YELLOW='\\033[1;33m'; BLUE='\\033[0;34m'; NC='\\033[0m'
info()    { echo -e "\${BLUE}[INFO]\${NC} \$1" | tee -a "\$OBS_LOG"; }
success() { echo -e "\${GREEN}[OK]\${NC} \$1"   | tee -a "\$OBS_LOG"; }
warn()    { echo -e "\${YELLOW}[WARN]\${NC} \$1" | tee -a "\$OBS_LOG"; }
error()   { echo -e "\${RED}[ERROR]\${NC} \$1"   | tee -a "\$OBS_LOG"; exit 1; }

# ===== VÉRIFICATION ROOT =====
[ "\$(id -u)" -ne 0 ] && error "Ce script doit être exécuté en tant que root (sudo)"

mkdir -p "\$(dirname \$OBS_LOG)"
echo "======================================" >> "\$OBS_LOG"
echo "Installation obstack Agent - \$(date)" >> "\$OBS_LOG"
echo "======================================" >> "\$OBS_LOG"

info "obstack Agent v\${OBS_VERSION} — Installation"
info "Entreprise: {$companyName} | Environnement: {$envName}"

# =============================================================================
# ÉTAPE 1: DÉTECTION DU TYPE DE MACHINE
# =============================================================================
info "Détection du type de machine..."

detect_machine_type() {
    local machine_type="unknown"
    local hypervisor=""
    local vm_uuid=""

    # Méthode 1: systemd-detect-virt (le plus fiable)
    if command -v systemd-detect-virt &>/dev/null; then
        local virt=\$(systemd-detect-virt 2>/dev/null || echo "none")
        case "\$virt" in
            none)        machine_type="physical" ;;
            kvm)         machine_type="vm_kvm";    hypervisor="KVM/QEMU" ;;
            vmware)      machine_type="vm_vmware";  hypervisor="VMware" ;;
            microsoft)   machine_type="vm_hyperv";  hypervisor="Hyper-V" ;;
            xen)         machine_type="vm_xen";     hypervisor="Xen" ;;
            docker|lxc*) machine_type="container" ;;
            *) machine_type="unknown" ;;
        esac
    fi

    # Méthode 2: DMI/SMBIOS (fallback)
    if [ "\$machine_type" = "unknown" ] && command -v dmidecode &>/dev/null; then
        local dmi=\$(dmidecode -s system-product-name 2>/dev/null | tr '[:upper:]' '[:lower:]')
        if echo "\$dmi" | grep -qi "vmware";        then machine_type="vm_vmware"; hypervisor="VMware"; fi
        if echo "\$dmi" | grep -qi "virtualbox";    then machine_type="vm_kvm";    hypervisor="VirtualBox"; fi
        if echo "\$dmi" | grep -qi "kvm\\|qemu";    then machine_type="vm_kvm";    hypervisor="KVM/QEMU"; fi
        if echo "\$dmi" | grep -qi "hyper-v\\|hvm"; then machine_type="vm_hyperv"; hypervisor="Hyper-V"; fi
        if echo "\$dmi" | grep -qi "amazon ec2";    then machine_type="cloud_aws"; fi
        if echo "\$dmi" | grep -qi "google compute"; then machine_type="cloud_gcp"; fi
        if echo "\$dmi" | grep -qi "microsoft azure"; then machine_type="cloud_azure"; fi
    fi

    # Méthode 3: Fichiers /sys (conteneurs)
    if [ -f /proc/1/environ ] && grep -q "container=lxc" /proc/1/environ 2>/dev/null; then
        machine_type="container"
    fi
    if [ -f /.dockerenv ]; then machine_type="container"; fi

    # UUID VM
    if command -v dmidecode &>/dev/null; then
        vm_uuid=\$(dmidecode -s system-uuid 2>/dev/null || echo "")
    fi

    echo "\${machine_type}|\${hypervisor}|\${vm_uuid}"
}

MACHINE_DETECTION=\$(detect_machine_type)
MACHINE_TYPE=\$(echo "\$MACHINE_DETECTION" | cut -d'|' -f1)
HYPERVISOR=\$(echo "\$MACHINE_DETECTION"   | cut -d'|' -f2)
VM_UUID=\$(echo "\$MACHINE_DETECTION"      | cut -d'|' -f3)

success "Type de machine: \$MACHINE_TYPE (Hyperviseur: \${HYPERVISOR:-N/A})"

# =============================================================================
# ÉTAPE 2: COLLECTE DES INFORMATIONS MATÉRIELLES
# =============================================================================
info "Collecte des informations matérielles..."

HOSTNAME_FQDN=\$(hostname -f 2>/dev/null || hostname)
HOSTNAME_SHORT=\$(hostname -s 2>/dev/null || hostname)
KERNEL=\$(uname -r)
ARCH=\$(uname -m)
OS_ID=\$(. /etc/os-release 2>/dev/null && echo "\$ID" || echo "unknown")
OS_VERSION=\$(. /etc/os-release 2>/dev/null && echo "\$VERSION_ID" || echo "")
OS_PRETTY=\$(. /etc/os-release 2>/dev/null && echo "\$PRETTY_NAME" || echo "Unknown")
CPU_MODEL=\$(grep -m1 "model name" /proc/cpuinfo 2>/dev/null | cut -d: -f2 | xargs || echo "")
CPU_CORES=\$(nproc --all 2>/dev/null || echo "0")
CPU_THREADS=\$(grep -c "^processor" /proc/cpuinfo 2>/dev/null || echo "0")
RAM_GB=\$(awk '/MemTotal/ {printf "%.1f", \$2/1024/1024}' /proc/meminfo)
SERIAL=\$(dmidecode -s system-serial-number 2>/dev/null || echo "")
MANUFACTURER=\$(dmidecode -s system-manufacturer 2>/dev/null || echo "")
PRODUCT=\$(dmidecode -s system-product-name 2>/dev/null || echo "")
BIOS=\$(dmidecode -s bios-version 2>/dev/null || echo "")
TIMEZONE=\$(timedatectl show -p Timezone --value 2>/dev/null || cat /etc/timezone 2>/dev/null || echo "")

# Interfaces réseau
NET_INTERFACES=\$(ip -j link show 2>/dev/null | python3 -c "
import sys, json
ifaces = json.load(sys.stdin)
result = []
for i in ifaces:
    if i['link_type'] != 'loopback':
        result.append({'name': i['ifname'], 'mac': i.get('address','')})
print(json.dumps(result))
" 2>/dev/null || echo "[]")

# Disques
DISK_INFO=\$(lsblk -J -o NAME,SIZE,TYPE,ROTA,TRAN 2>/dev/null | python3 -c "
import sys, json
data = json.load(sys.stdin)
disks = []
for d in data.get('blockdevices', []):
    if d['type'] == 'disk':
        disk_type = 'HDD' if d.get('rota') == '1' else 'SSD'
        tran = d.get('tran', '')
        if 'nvme' in tran: disk_type = 'NVMe'
        disks.append({'name': d['name'], 'size': d['size'], 'type': disk_type, 'transport': tran})
print(json.dumps(disks))
" 2>/dev/null || echo "[]")

success "Matériel détecté: \$CPU_CORES cores, \${RAM_GB}GB RAM"

# =============================================================================
# ÉTAPE 3: DÉTECTION DES TECHNOLOGIES
# =============================================================================
info "Détection des technologies et services installés..."

TECHNOLOGIES="[]"

detect_technologies() {
    local techs="[]"

    python3 << 'PYEOF'
import json, subprocess, os, glob, re

def run(cmd):
    try:
        return subprocess.check_output(cmd, shell=True, stderr=subprocess.DEVNULL, timeout=5).decode().strip()
    except:
        return ""

def check_port(port):
    return run(f"ss -tlnp 'sport = :{port}' | grep -c LISTEN") != "0"

def service_active(name):
    return run(f"systemctl is-active {name} 2>/dev/null") == "active"

def get_version(cmd):
    try:
        out = run(cmd)
        m = re.search(r'([0-9]+\\.[0-9]+[\\.[0-9]+]*)', out)
        return m.group(1) if m else out[:20]
    except:
        return None

techs = []

# ===== JAVA & APP SERVERS =====
java_ver = get_version("java -version 2>&1 | head -1")
if java_ver: techs.append({"type": "java", "version": java_ver, "category": "runtime"})

for svc in ["tomcat9", "tomcat10", "tomcat", "tomcat8"]:
    if service_active(svc):
        ver = get_version(f"find /opt/tomcat /usr/share/tomcat* -name 'RELEASE-NOTES' 2>/dev/null | head -1 | xargs cat 2>/dev/null | grep -oP 'Apache Tomcat/\\K[^\\s]+'")
        port = run("ss -tlnp | grep java | awk '{print $4}' | grep -oP ':\\K[0-9]+' | head -1") or "8080"
        techs.append({"type": "tomcat", "version": ver, "service": svc, "port": port, "status": "running"})
        break

if run("which jboss-cli.sh 2>/dev/null") or service_active("wildfly"):
    ver = get_version("find /opt/wildfly /opt/jboss -name 'version.txt' 2>/dev/null | head -1 | xargs cat 2>/dev/null")
    techs.append({"type": "wildfly", "version": ver, "status": "running" if service_active("wildfly") else "installed"})

# ===== WEB SERVERS =====
if run("which nginx 2>/dev/null"):
    ver = get_version("nginx -v 2>&1")
    techs.append({"type": "nginx", "version": ver, "port": "80/443", "status": "running" if service_active("nginx") else "installed"})

if run("which apache2 2>/dev/null") or run("which httpd 2>/dev/null"):
    ver = get_version("apache2 -v 2>&1 || httpd -v 2>&1")
    svc = "apache2" if service_active("apache2") else "httpd"
    techs.append({"type": "apache", "version": ver, "port": "80/443", "status": "running" if service_active(svc) else "installed"})

if run("which haproxy 2>/dev/null"):
    ver = get_version("haproxy -v 2>&1")
    techs.append({"type": "haproxy", "version": ver, "status": "running" if service_active("haproxy") else "installed"})

# ===== DATABASES =====
# Oracle
oracle_home = run("find /opt/oracle /u01 -name 'sqlplus' 2>/dev/null | head -1 | xargs dirname 2>/dev/null")
if oracle_home:
    ver = get_version(f"{oracle_home}/sqlplus -v 2>&1")
    techs.append({"type": "oracle", "version": ver, "port": "1521", "status": "running" if service_active("oracle") else "installed"})

# PostgreSQL
if run("which psql 2>/dev/null"):
    ver = get_version("psql --version 2>&1")
    port = run("pg_lsclusters 2>/dev/null | awk 'NR>1{print $3}' | head -1") or "5432"
    status = "running" if any(service_active(f"postgresql{s}") or service_active("postgresql") for s in ["", "@14-main", "@15-main", "@16-main"]) else "installed"
    techs.append({"type": "postgresql", "version": ver, "port": port, "status": status})

# MySQL/MariaDB
if run("which mysql 2>/dev/null"):
    ver = get_version("mysql --version 2>&1")
    db_type = "mariadb" if "MariaDB" in ver else "mysql"
    status = "running" if service_active(db_type) else "installed"
    techs.append({"type": db_type, "version": ver, "port": "3306", "status": status})

# MongoDB
if run("which mongod 2>/dev/null"):
    ver = get_version("mongod --version 2>&1")
    techs.append({"type": "mongodb", "version": ver, "port": "27017", "status": "running" if service_active("mongod") else "installed"})

# Redis
if run("which redis-server 2>/dev/null"):
    ver = get_version("redis-server --version 2>&1")
    techs.append({"type": "redis", "version": ver, "port": "6379", "status": "running" if service_active("redis") else "installed"})

# Elasticsearch
if check_port(9200) or run("which elasticsearch 2>/dev/null"):
    ver = get_version("curl -s http://localhost:9200 2>/dev/null | python3 -c \\"import sys,json; d=json.load(sys.stdin); print(d['version']['number'])\\" 2>/dev/null")
    techs.append({"type": "elasticsearch", "version": ver, "port": "9200", "status": "running" if check_port(9200) else "installed"})

# ===== RUNTIMES =====
if run("which node 2>/dev/null"):
    ver = get_version("node --version")
    techs.append({"type": "nodejs", "version": ver})

if run("which python3 2>/dev/null"):
    ver = get_version("python3 --version")
    techs.append({"type": "python", "version": ver})

if run("which php 2>/dev/null"):
    ver = get_version("php --version")
    if service_active("php8.3-fpm") or service_active("php8.2-fpm") or service_active("php-fpm"):
        techs.append({"type": "php_fpm", "version": ver, "status": "running"})

if run("which dotnet 2>/dev/null"):
    ver = get_version("dotnet --version")
    techs.append({"type": "dotnet", "version": ver})

# ===== MESSAGE BROKERS =====
if run("which kafka-server-start.sh 2>/dev/null") or service_active("kafka"):
    techs.append({"type": "kafka", "port": "9092", "status": "running" if service_active("kafka") else "installed"})

if run("which rabbitmq-server 2>/dev/null") or service_active("rabbitmq-server"):
    ver = get_version("rabbitmqctl version 2>&1")
    techs.append({"type": "rabbitmq", "version": ver, "port": "5672", "status": "running" if service_active("rabbitmq-server") else "installed"})

# ===== CONTENEURISATION =====
if run("which docker 2>/dev/null"):
    ver = get_version("docker version --format '{{.Server.Version}}' 2>/dev/null")
    containers = run("docker ps --format '{{.Names}}' 2>/dev/null") or ""
    container_list = [c for c in containers.split("\\n") if c]
    techs.append({"type": "docker", "version": ver, "running_containers": len(container_list), "containers": container_list[:20]})

if run("which kubectl 2>/dev/null") or os.path.exists("/etc/kubernetes"):
    ver = get_version("kubectl version --client --short 2>/dev/null")
    role = "master" if os.path.exists("/etc/kubernetes/manifests/kube-apiserver.yaml") else "worker"
    techs.append({"type": "kubernetes", "version": ver, "node_role": role})

if run("which containerd 2>/dev/null"):
    ver = get_version("containerd --version 2>&1")
    techs.append({"type": "containerd", "version": ver})

# ===== MONITORING =====
if service_active("prometheus") or check_port(9090):
    techs.append({"type": "prometheus", "port": "9090", "status": "running"})
if service_active("grafana-server") or check_port(3000):
    techs.append({"type": "grafana", "port": "3000", "status": "running"})

print(json.dumps(techs))
PYEOF
}

TECHNOLOGIES=\$(detect_technologies 2>/dev/null || echo "[]")
TECH_COUNT=\$(echo "\$TECHNOLOGIES" | python3 -c "import sys,json; print(len(json.load(sys.stdin)))" 2>/dev/null || echo "0")

success "\$TECH_COUNT technologie(s) détectée(s)"
info "Modules activés: \${OBS_MODULES:-aucun}"

install_selected_modules() {
    if [ -z "\${OBS_MODULES:-}" ]; then
        warn "Aucun module additionnel sélectionné"
        return
    fi

    if command -v apt-get &>/dev/null; then
        apt-get update -qq || true
    fi

    if [[ "\${OBS_MODULES}" == *"prometheus"* ]]; then
        info "Activation du module Prometheus"
        if command -v apt-get &>/dev/null; then
            apt-get install -y prometheus-node-exporter 2>/dev/null || warn "Impossible d'installer prometheus-node-exporter automatiquement"
            systemctl enable --now prometheus-node-exporter 2>/dev/null || warn "Impossible de démarrer Prometheus Node Exporter"
        else
            warn "apt-get introuvable, installation de Prometheus non automatisée"
        fi
    fi

    if [[ "\${OBS_MODULES}" == *"opentelemetry"* ]]; then
        info "Activation du module OpenTelemetry + eBPF"
        if command -v apt-get &>/dev/null; then
            apt-get install -y bpfcc-tools linux-headers-$(uname -r) 2>/dev/null || warn "Impossible d'installer les dépendances OpenTelemetry/eBPF automatiquement"
        else
            warn "apt-get introuvable, installation OpenTelemetry/eBPF non automatisée"
        fi
    fi

    if [[ "\${OBS_MODULES}" == *"loki"* ]]; then
        info "Activation du module Loki"
        if command -v apt-get &>/dev/null; then
            apt-get install -y curl 2>/dev/null || warn "Impossible d'installer curl pour Loki"
        fi
    fi

    if [[ "\${OBS_MODULES}" == *"jaeger"* ]]; then
        info "Activation du module Jaeger"
        if command -v apt-get &>/dev/null; then
            apt-get install -y curl 2>/dev/null || warn "Impossible d'installer curl pour Jaeger"
        fi
    fi
}

# =============================================================================
# ÉTAPE 4: DÉTECTION KUBERNETES
# =============================================================================
IS_K8S_NODE="false"
K8S_ROLE="worker"
K8S_NODE_INFO="{}"

if command -v kubectl &>/dev/null && [ -f /etc/kubernetes/admin.conf ] 2>/dev/null; then
    IS_K8S_NODE="true"
    if [ -f /etc/kubernetes/manifests/kube-apiserver.yaml ]; then
        K8S_ROLE="master"
    fi

    KUBE_NODE_NAME=\$(kubectl --kubeconfig=/etc/kubernetes/admin.conf get nodes -o jsonpath='{.items[0].metadata.name}' 2>/dev/null || hostname)
    K8S_VERSION=\$(kubectl --kubeconfig=/etc/kubernetes/admin.conf version --short 2>/dev/null | grep "Server Version" | awk '{print \$3}' || echo "")
    K8S_RUNTIME=\$(kubectl --kubeconfig=/etc/kubernetes/admin.conf get node "\$KUBE_NODE_NAME" -o jsonpath='{.status.nodeInfo.containerRuntimeVersion}' 2>/dev/null || echo "")

    info "Node Kubernetes détecté: rôle \$K8S_ROLE, version \$K8S_VERSION"
fi

# =============================================================================
# ÉTAPE 5: INSTALLATION DE L'AGENT
# =============================================================================
info "Installation de l'agent obstack..."
install_selected_modules

# Créer utilisateur agent
if ! id "\$OBS_USER" &>/dev/null; then
    useradd -r -s /sbin/nologin -d "\$OBS_AGENT_DIR" "\$OBS_USER"
fi

# Créer répertoire
mkdir -p "\$OBS_AGENT_DIR"/{bin,config,logs,data}
chown -R "\$OBS_USER:\$OBS_USER" "\$OBS_AGENT_DIR"

# Fichier de configuration de l'agent
cat > "\$OBS_AGENT_DIR/config/agent.json" << AGENTCONF
{
    "token":          "\$OBS_TOKEN",
    "api_url":        "\$OBS_API_URL",
    "company":        "\$OBS_COMPANY",
    "environment":    "\$OBS_ENV",
    "hostname":       "\$HOSTNAME_FQDN",
    "collect_interval": 60,
    "heartbeat_interval": 30,
    "machine_type":   "\$MACHINE_TYPE",
    "hypervisor":     "\$HYPERVISOR",
    "vm_uuid":        "\$VM_UUID",
    "is_k8s_node":    \$IS_K8S_NODE,
    "k8s_role":       "\$K8S_ROLE",
    "enabled_modules": \$OBS_MODULES_JSON,
    "version":        "\$OBS_VERSION"
}
AGENTCONF

# Script de collecte principal
cat > "\$OBS_AGENT_DIR/bin/collect.sh" << 'COLLECTSH'
#!/usr/bin/env bash
CONFIG=\$(cat /opt/obstack-agent/config/agent.json)
API_URL=\$(echo "\$CONFIG" | python3 -c "import sys,json; print(json.load(sys.stdin)['api_url'])")
TOKEN=\$(echo "\$CONFIG" | python3 -c "import sys,json; print(json.load(sys.stdin)['token'])")

collect_metrics() {
    python3 << 'PYMETRICS'
import json, subprocess, time, psutil, socket

def run(cmd):
    try:
        return subprocess.check_output(cmd, shell=True, stderr=subprocess.DEVNULL, timeout=10).decode().strip()
    except:
        return ""

metrics = {
    "timestamp": int(time.time()),
    "cpu_percent": psutil.cpu_percent(interval=1),
    "cpu_per_core": psutil.cpu_percent(interval=1, percpu=True),
    "memory": {
        "total_mb": psutil.virtual_memory().total // 1024 // 1024,
        "used_mb":  psutil.virtual_memory().used  // 1024 // 1024,
        "percent":  psutil.virtual_memory().percent,
    },
    "swap": {
        "total_mb": psutil.swap_memory().total // 1024 // 1024,
        "used_mb":  psutil.swap_memory().used  // 1024 // 1024,
        "percent":  psutil.swap_memory().percent,
    },
    "disks": [],
    "network": {"bytes_sent": 0, "bytes_recv": 0, "latency_ms": {}},
    "load_average": list(psutil.getloadavg()),
    "uptime_seconds": int(time.time() - psutil.boot_time()),
    "process_count": len(psutil.pids()),
    "open_files": 0,
    "connections": len(psutil.net_connections()),
}

# Disques
for part in psutil.disk_partitions():
    if part.fstype in ('ext4', 'xfs', 'btrfs', 'zfs', 'nfs', 'nfs4'):
        try:
            usage = psutil.disk_usage(part.mountpoint)
            io    = psutil.disk_io_counters(perdisk=True).get(part.device.replace('/dev/',''), None)
            metrics["disks"].append({
                "mountpoint":   part.mountpoint,
                "device":       part.device,
                "fstype":       part.fstype,
                "total_gb":     round(usage.total / 1e9, 1),
                "used_gb":      round(usage.used  / 1e9, 1),
                "used_percent": usage.percent,
                "read_mb_s":    round(io.read_bytes  / 1e6, 2) if io else None,
                "write_mb_s":   round(io.write_bytes / 1e6, 2) if io else None,
            })
        except: pass

# Latences réseau
import subprocess, time
for host, label in [("8.8.8.8", "internet"), ("127.0.0.1", "loopback")]:
    try:
        start = time.time()
        subprocess.run(["ping", "-c", "1", "-W", "2", host], capture_output=True, timeout=3)
        metrics["network"]["latency_ms"][label] = round((time.time()-start)*1000, 1)
    except: pass

# Network I/O
net = psutil.net_io_counters()
metrics["network"]["bytes_sent"] = net.bytes_sent
metrics["network"]["bytes_recv"] = net.bytes_recv

print(json.dumps(metrics))
PYMETRICS
}

METRICS=\$(collect_metrics)
curl -sf -X POST "\$API_URL/agent/metrics" \
    -H "Authorization: Bearer \$TOKEN" \
    -H "Content-Type: application/json" \
    -d "\$METRICS" || true
COLLECTSH

chmod +x "\$OBS_AGENT_DIR/bin/collect.sh"

# Installer psutil si absent
pip3 install psutil --quiet 2>/dev/null || pip install psutil --quiet 2>/dev/null || true

# Service systemd
cat > /etc/systemd/system/obstack-agent.service << SYSTEMD
[Unit]
Description=obstack Monitoring Agent
After=network.target
Wants=network.target

[Service]
Type=simple
User=\$OBS_USER
ExecStart=/bin/bash \$OBS_AGENT_DIR/bin/collect.sh
Restart=always
RestartSec=60
StandardOutput=append:\$OBS_AGENT_DIR/logs/agent.log
StandardError=append:\$OBS_AGENT_DIR/logs/agent-error.log

[Install]
WantedBy=multi-user.target
SYSTEMD

# Timer systemd (toutes les 60 secondes)
cat > /etc/systemd/system/obstack-agent.timer << TIMER
[Unit]
Description=obstack Agent Timer
Requires=obstack-agent.service

[Timer]
OnBootSec=30s
OnUnitActiveSec=60s
Unit=obstack-agent.service

[Install]
WantedBy=timers.target
TIMER

systemctl daemon-reload
systemctl enable --now obstack-agent.timer

success "Agent installé et démarré"

# =============================================================================
# ÉTAPE 6: ENREGISTREMENT AUPRÈS DE LA PLATEFORME
# =============================================================================
info "Enregistrement de l'agent auprès de la plateforme..."

REGISTRATION_PAYLOAD=\$(python3 -c "
import json, os
payload = {
    'token':          '\$OBS_TOKEN',
    'hostname':       '\$HOSTNAME_FQDN',
    'hostname_short': '\$HOSTNAME_SHORT',
    'machine_type':   '\$MACHINE_TYPE',
    'hypervisor':     '\$HYPERVISOR',
    'vm_uuid':        '\$VM_UUID',
    'os_id':          '\$OS_ID',
    'os_version':     '\$OS_VERSION',
    'os_pretty':      '\$OS_PRETTY',
    'kernel':         '\$KERNEL',
    'architecture':   '\$ARCH',
    'cpu_model':      '\$CPU_MODEL',
    'cpu_cores':      \$CPU_CORES,
    'cpu_threads':    \$CPU_THREADS,
    'ram_gb':         '\$RAM_GB',
    'serial':         '\$SERIAL',
    'manufacturer':   '\$MANUFACTURER',
    'product':        '\$PRODUCT',
    'bios':           '\$BIOS',
    'timezone':       '\$TIMEZONE',
    'is_k8s_node':    \$IS_K8S_NODE,
    'k8s_role':       '\$K8S_ROLE',
    'technologies':   \$TECHNOLOGIES,
    'network_interfaces': \$NET_INTERFACES,
    'disks':          \$DISK_INFO,
    'agent_version':  '\$OBS_VERSION',
    'enabled_modules': json.loads(os.environ.get('OBS_MODULES_JSON', '[]')),
}
print(json.dumps(payload))
")

HTTP_CODE=\$(curl -sf -o /tmp/obs_reg_response.json -w "%{http_code}" \
    -X POST "\$OBS_API_URL/agent/register" \
    -H "Authorization: Bearer \$OBS_TOKEN" \
    -H "Content-Type: application/json" \
    -d "\$REGISTRATION_PAYLOAD" 2>/dev/null || echo "000")

if [ "\$HTTP_CODE" = "200" ] || [ "\$HTTP_CODE" = "201" ]; then
    success "Agent enregistré avec succès"
    cat /tmp/obs_reg_response.json | python3 -m json.tool 2>/dev/null || true
else
    warn "Enregistrement HTTP \$HTTP_CODE — l'agent fonctionnera en mode local"
    warn "Réponse: \$(cat /tmp/obs_reg_response.json 2>/dev/null)"
fi

# =============================================================================
# RÉSUMÉ FINAL
# =============================================================================
echo ""
echo "======================================================"
echo "  ✓ obstack Agent installé avec succès"
echo "======================================================"
echo "  Entreprise    : {$companyName}"
echo "  Environnement : {$envName}"
echo "  Hostname      : \$HOSTNAME_FQDN"
echo "  Machine       : \$MACHINE_TYPE (\${HYPERVISOR:-Bare Metal})"
echo "  Technologies  : \$TECH_COUNT détectée(s)"
echo "  K8s Node      : \$IS_K8S_NODE (\$K8S_ROLE)"
echo "  Token         : {$token->getMaskedToken()}"
echo "  Logs          : \$OBS_AGENT_DIR/logs/"
echo ""
echo "  Statut du service:"
systemctl status obstack-agent.timer --no-pager -l 2>/dev/null || true
echo "======================================================"
BASH;

        $token->setInstallScript($script);
        return $script;
    }

    private function validatePublicKeyUrl(string $url, string $expectedFingerprint): void
    {
        $publicKeyData = $this->downloadPublicKey($url);
        $fingerprint = $this->getPublicKeyFingerprint($publicKeyData);

        if (!$fingerprint) {
            throw new \RuntimeException(sprintf('Impossible de calculer le fingerprint de la clé publique téléchargée depuis %s.', $url));
        }

        $normalizedExpected = $this->normalizeFingerprint($expectedFingerprint);
        $normalizedActual   = $this->normalizeFingerprint($fingerprint);

        if ($normalizedExpected !== $normalizedActual) {
            throw new \RuntimeException(sprintf('Fingerprint de clé publique incompatible : attendu %s, obtenu %s.', $normalizedExpected, $normalizedActual));
        }
    }

    private function normalizeFingerprint(string $fingerprint): string
    {
        $normalized = strtoupper(preg_replace('/[^A-F0-9]/i', '', $fingerprint));

        if ($normalized === '') {
            throw new \RuntimeException('Fingerprint attendu vide ou invalide.');
        }

        if (!ctype_xdigit($normalized)) {
            throw new \RuntimeException(sprintf('Fingerprint attendu contient des caractères invalides : %s.', $fingerprint));
        }

        $length = strlen($normalized);
        if (!in_array($length, [32, 40, 64], true)) {
            throw new \RuntimeException(sprintf('Fingerprint attendu a une longueur invalide (%d caractères) : %s.', $length, $fingerprint));
        }

        return $normalized;
    }

    private function downloadPublicKey(string $url): string
    {
        if (ini_get('allow_url_fopen')) {
            $data = @file_get_contents($url);
            if ($data !== false) {
                return $data;
            }
        }

        if ($this->isCommandAvailable('curl')) {
            $data = shell_exec(sprintf('curl -fsSL %s', escapeshellarg($url)));
            if (is_string($data) && $data !== '') {
                return $data;
            }
        }

        throw new \RuntimeException(sprintf('Impossible de télécharger la clé publique depuis %s.', $url));
    }

    private function getPublicKeyFingerprint(string $publicKeyData): ?string
    {
        if (!$this->isCommandAvailable('gpg')) {
            throw new \RuntimeException('gpg est requis pour vérifier le fingerprint de la clé publique.');
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'obstack_pubkey_');
        if ($tmpFile === false) {
            throw new \RuntimeException('Impossible de créer un fichier temporaire pour la clé publique.');
        }

        file_put_contents($tmpFile, $publicKeyData);
        $command = sprintf('gpg --batch --with-colons --import-options show-only --import %s 2>/dev/null', escapeshellarg($tmpFile));
        $output = shell_exec($command);
        @unlink($tmpFile);

        if (!is_string($output) || $output === '') {
            return null;
        }

        if (preg_match('/^fpr:[^:]*:([0-9A-Fa-f]{32,})/m', $output, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    private function isCommandAvailable(string $command): bool
    {
        return is_string(shell_exec(sprintf('command -v %s 2>/dev/null', escapeshellarg($command)))) && trim(shell_exec(sprintf('command -v %s 2>/dev/null', escapeshellarg($command)))) !== '';
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s');
    }
}
