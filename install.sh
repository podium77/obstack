#!/usr/bin/env bash
# =============================================================================
# obstack v1 — Script d'installation sur Debian 13
# Multi-tenant | Kubernetes | Auto-remédiation | PyRCA | Knowledge Graph
# Usage: sudo bash install.sh
# =============================================================================
# set -euo pipefail

# ─── Couleurs ────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
BLUE='\033[0;34m'; CYAN='\033[0;36m'; NC='\033[0m'
info()    { echo -e "${BLUE}[INFO]${NC}  $1"; }
success() { echo -e "${GREEN}[OK]${NC}    $1"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $1"; }
error()   { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }
step()    { echo -e "\n${CYAN}━━━━ $1 ━━━━${NC}"; }

# ─── Variables ───────────────────────────────────────────────────────────────
APP_USER="obstack"
APP_DIR="/var/www/obstack"
PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
DB_NAME="obstack"
DB_USER="obstack"
DB_PASS="$(openssl rand -hex 20)"
APP_SECRET="$(openssl rand -hex 32)"
HOSTNAME_FQDN="$(hostname -f 2>/dev/null || hostname)"
BASE_URL="http://${HOSTNAME_FQDN}"

# ─── Vérifications ───────────────────────────────────────────────────────────
[ "$(id -u)" -ne 0 ] && error "Ce script doit être exécuté en root (sudo bash install.sh)"
[ -f /etc/debian_version ] || warn "Ce script est optimisé pour Debian/Ubuntu"

echo ""
echo "╔══════════════════════════════════════════════════════╗"
echo "║       obstack v1 — Installation Debian 13            ║"
echo "║    Multi-tenant · Kubernetes · PyRCA · K-Graph       ║"
echo "╚══════════════════════════════════════════════════════╝"
echo ""

# ─── ÉTAPE 1: Paquets système ─────────────────────────────────────────────
step "Installation des paquets système"

apt-get update -qq
DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
    curl wget git unzip ca-certificates gnupg lsb-release \
    supervisor redis-server \
    postgresql postgresql-contrib \
    nginx \
    openssh-client openssh-server \
    python3 python3-pip \
    dmidecode \
    at \
    php${PHP_VER} \
    php${PHP_VER}-fpm \
    php${PHP_VER}-cli \
    php${PHP_VER}-pgsql \
    php${PHP_VER}-ldap \
    php${PHP_VER}-intl \
    php${PHP_VER}-xml \
    php${PHP_VER}-curl \
    php${PHP_VER}-redis \
    php${PHP_VER}-mbstring \
    php${PHP_VER}-zip \
    php${PHP_VER}-bcmath \
    php${PHP_VER}-ssh2 \
    libssh2-1-dev

# psutil pour les agents
pip3 install psutil --break-system-packages 2>/dev/null || pip3 install psutil

success "Paquets système installés"

# ─── ÉTAPE 2: Composer ───────────────────────────────────────────────────────
step "Installation de Composer"

if ! command -v composer &>/dev/null; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi
success "Composer $(composer --version --no-ansi 2>/dev/null | head -1)"

# ─── ÉTAPE 3: Symfony CLI ────────────────────────────────────────────────────
step "Installation de Symfony CLI"

if ! command -v symfony &>/dev/null; then
    curl -sS https://get.symfony.com/cli/installer | bash
    mv ~/.symfony*/bin/symfony /usr/local/bin/symfony 2>/dev/null || true
fi
success "Symfony CLI installé"

# ─── ÉTAPE 4: Utilisateur applicatif ─────────────────────────────────────────
step "Création de l'utilisateur applicatif"

if ! id "$APP_USER" &>/dev/null; then
    useradd -r -m -d /var/lib/obstack -s /bin/bash "$APP_USER"
fi

# Clé SSH pour les connexions agents
SSH_KEY="/var/lib/obstack/.ssh/id_rsa"
if [ ! -f "$SSH_KEY" ]; then
    sudo -u "$APP_USER" mkdir -p /var/lib/obstack/.ssh
    sudo -u "$APP_USER" ssh-keygen -t rsa -b 4096 -N '' \
        -f "$SSH_KEY" \
        -C "obstack-agent@$(hostname)"
    chmod 700 /var/lib/obstack/.ssh
    chmod 600 "$SSH_KEY"
fi

success "Utilisateur $APP_USER créé"

# ─── ÉTAPE 5: PostgreSQL ─────────────────────────────────────────────────────
step "Configuration PostgreSQL"

systemctl enable --now postgresql

sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname='${DB_USER}'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE USER ${DB_USER} WITH PASSWORD '${DB_PASS}';"

sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'" | grep -q 1 || \
    sudo -u postgres psql -c "CREATE DATABASE ${DB_NAME} OWNER ${DB_USER};"

sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE ${DB_NAME} TO ${DB_USER};"

success "PostgreSQL configuré (db: ${DB_NAME}, user: ${DB_USER})"

# ─── ÉTAPE 6: Redis ──────────────────────────────────────────────────────────
step "Configuration Redis"

# Limiter la mémoire Redis
sed -i 's/^# maxmemory .*/maxmemory 512mb/' /etc/redis/redis.conf
sed -i 's/^# maxmemory-policy .*/maxmemory-policy allkeys-lru/' /etc/redis/redis.conf
systemctl enable --now redis-server

success "Redis configuré et démarré"

# ─── ÉTAPE 7: Application Symfony ────────────────────────────────────────────
step "Déploiement de l'application Symfony"

# Copier les fichiers si pas déjà en place
if [ ! -d "${APP_DIR}/src" ]; then
    cp -r "$(dirname "$0")/.." "${APP_DIR}/" 2>/dev/null || {
        warn "Copiez manuellement les sources dans ${APP_DIR}/"
    }
fi

# Créer le fichier .env.local
cat > "${APP_DIR}/.env.local" << ENV
APP_ENV=prod
APP_SECRET=${APP_SECRET}
APP_BASE_URL=${BASE_URL}
DATABASE_URL="postgresql://${DB_USER}:${DB_PASS}@127.0.0.1:5432/${DB_NAME}?serverVersion=16&charset=utf8"
MESSENGER_TRANSPORT_DSN=redis://127.0.0.1:6379/messages
REDIS_URL=redis://127.0.0.1:6379
MAILER_DSN=smtp://localhost:25
MAIL_FROM=obstack@$(hostname -d 2>/dev/null || echo 'company.local')
MAIL_ADMIN=admin@$(hostname -d 2>/dev/null || echo 'company.local')
METRIC_RETENTION_DAYS=90
ENV

chown -R "$APP_USER:$APP_USER" "$APP_DIR"

# Installation des dépendances PHP
cd "$APP_DIR"
sudo -u "$APP_USER" composer install --no-dev --optimize-autoloader --no-interaction -q

# Cache Symfony
sudo -u "$APP_USER" php bin/console cache:clear --env=prod --no-debug -q
sudo -u "$APP_USER" php bin/console cache:warmup --env=prod --no-debug -q

# Migrations BDD
sudo -u "$APP_USER" php bin/console doctrine:migrations:migrate --no-interaction --env=prod

success "Application Symfony déployée"

# ─── ÉTAPE 8: PHP-FPM ────────────────────────────────────────────────────────
step "Configuration PHP-FPM"

cat > "/etc/php/${PHP_VER}/fpm/pool.d/obstack.conf" << 'PHPFPM'
[obstack]
user = obstack
group = obstack
listen = /run/php/obstack.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 8
pm.max_requests = 500
php_admin_value[error_log] = /var/log/obstack/php-fpm-error.log
php_admin_flag[log_errors] = on
php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 20M
php_admin_value[post_max_size] = 20M
php_admin_value[max_execution_time] = 120
php_admin_value[opcache.enable] = 1
php_admin_value[opcache.memory_consumption] = 256
php_admin_value[opcache.max_accelerated_files] = 20000
PHPFPM

# Désactiver le pool www par défaut
mv "/etc/php/${PHP_VER}/fpm/pool.d/www.conf" \
   "/etc/php/${PHP_VER}/fpm/pool.d/www.conf.disabled" 2>/dev/null || true

mkdir -p /var/log/obstack
chown -R "$APP_USER:$APP_USER" /var/log/obstack

systemctl enable --now "php${PHP_VER}-fpm"
success "PHP-FPM configuré"

# ─── ÉTAPE 9: Nginx ──────────────────────────────────────────────────────────
step "Configuration Nginx"

cat > /etc/nginx/sites-available/obstack << NGINX
server {
    listen 80;
    server_name _;
    root ${APP_DIR}/public;
    index index.php;
    client_max_body_size 20M;

    gzip on;
    gzip_types text/plain text/css application/json application/javascript;

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2?)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files \$uri =404;
    }

    location / {
        try_files \$uri /index.php\$is_args\$args;
    }

    location ~ ^/index\\.php(/|\$) {
        fastcgi_pass unix:/run/php/obstack.sock;
        fastcgi_split_path_info ^(.+\\.php)(/.*)\$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        fastcgi_read_timeout 120;
        internal;
    }

    location ~ \\.php\$ { return 404; }

    # SSE — pas de buffering
    location /api/dashboard/stream {
        fastcgi_pass unix:/run/php/obstack.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \${APP_DIR}/public/index.php;
        fastcgi_buffering off;
        add_header X-Accel-Buffering no;
    }

    add_header X-Frame-Options SAMEORIGIN always;
    add_header X-Content-Type-Options nosniff always;

    access_log /var/log/obstack/nginx-access.log;
    error_log  /var/log/obstack/nginx-error.log;
}
NGINX

rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/obstack /etc/nginx/sites-enabled/
nginx -t && systemctl enable --now nginx

success "Nginx configuré"

# ─── ÉTAPE 10: Supervisor (workers Messenger + Scheduler) ────────────────────
step "Configuration Supervisor"

cat > /etc/supervisor/conf.d/obstack.conf << SUPERVISOR
[program:obs-worker-async]
command=php ${APP_DIR}/bin/console messenger:consume async --time-limit=3600 --env=prod
user=${APP_USER}
numprocs=2
process_name=%(program_name)s_%(process_num)02d
autostart=true
autorestart=true
startsecs=5
stopwaitsecs=30
stdout_logfile=/var/log/obstack/worker-async.log
stderr_logfile=/var/log/obstack/worker-async-err.log

[program:obs-worker-remediation]
command=php ${APP_DIR}/bin/console messenger:consume remediation --time-limit=3600 --env=prod
user=${APP_USER}
numprocs=1
autostart=true
autorestart=true
startsecs=5
stopwaitsecs=60
stdout_logfile=/var/log/obstack/worker-remediation.log
stderr_logfile=/var/log/obstack/worker-remediation-err.log

[program:obs-worker-metrics]
command=php ${APP_DIR}/bin/console messenger:consume metrics --time-limit=3600 --env=prod
user=${APP_USER}
numprocs=2
process_name=%(program_name)s_%(process_num)02d
autostart=true
autorestart=true
startsecs=5
stdout_logfile=/var/log/obstack/worker-metrics.log
stderr_logfile=/var/log/obstack/worker-metrics-err.log

[program:obs-scheduler]
command=php ${APP_DIR}/bin/console scheduler:run --env=prod
user=${APP_USER}
numprocs=1
autostart=true
autorestart=true
startsecs=5
stdout_logfile=/var/log/obstack/scheduler.log
stderr_logfile=/var/log/obstack/scheduler-err.log

[group:obstack]
programs=obs-worker-async,obs-worker-remediation,obs-worker-metrics,obs-scheduler
priority=999
SUPERVISOR

systemctl enable --now supervisor
supervisorctl reread
supervisorctl update
supervisorctl start obstack:* 2>/dev/null || true

success "Supervisor configuré (4 workers démarrés)"

# ─── ÉTAPE 11: Logrotate ─────────────────────────────────────────────────────
step "Configuration Logrotate"

cat > /etc/logrotate.d/obstack << 'LOGROTATE'
/var/log/obstack/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 obstack obstack
    sharedscripts
    postrotate
        supervisorctl signal USR1 obstack:* > /dev/null 2>&1 || true
    endscript
}
LOGROTATE

success "Logrotate configuré"

# ─── ÉTAPE 12: Sudo pour l'utilisateur applicatif ────────────────────────────
step "Configuration sudo"

cat > /etc/sudoers.d/obstack << 'SUDOERS'
# obstack — commandes autorisées pour l'agent de remédiation
# Ceci s'applique au compte obstack sur ce serveur de plateforme
Defaults:obstack !requiretty
obstack ALL=(ALL) NOPASSWD: \
    /bin/systemctl start *, \
    /bin/systemctl stop *, \
    /bin/systemctl restart *, \
    /bin/systemctl is-active *, \
    /sbin/shutdown *, \
    /usr/bin/find * -delete, \
    /bin/rm -f *, \
    /bin/sync, \
    /bin/echo * > /proc/sys/vm/drop_caches, \
    /sbin/swapon -a, \
    /sbin/swapoff -a, \
    /usr/bin/at *
SUDOERS
chmod 440 /etc/sudoers.d/obstack

success "Sudo configuré"

# ─── ÉTAPE 13: Firewall (optionnel) ──────────────────────────────────────────
step "Configuration Firewall (ufw)"

if command -v ufw &>/dev/null; then
    ufw allow 80/tcp   comment 'obstack HTTP'  2>/dev/null || true
    ufw allow 443/tcp  comment 'obstack HTTPS' 2>/dev/null || true
    ufw allow 22/tcp   comment 'SSH'               2>/dev/null || true
    success "Règles ufw ajoutées"
else
    warn "ufw non installé — configurez manuellement votre firewall"
fi

# ─── RÉCAPITULATIF ───────────────────────────────────────────────────────────
echo ""
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║           ✓  obstack v1 installé avec succès !               ║"
echo "╠══════════════════════════════════════════════════════════════╣"
printf "║  %-60s ║\n" "URL:          ${BASE_URL}"
printf "║  %-60s ║\n" "DB user:      ${DB_USER}"
printf "║  %-60s ║\n" "DB password:  ${DB_PASS}"
printf "║  %-60s ║\n" "App dir:      ${APP_DIR}"
printf "║  %-60s ║\n" "Logs:         /var/log/obstack/"
echo "╠══════════════════════════════════════════════════════════════╣"
echo "║  Clé SSH publique de l'agent (à déployer sur les cibles):    ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""
cat /var/lib/obstack/.ssh/id_rsa.pub
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo " Prochaines étapes:"
echo "  1. Accédez à ${BASE_URL}/register pour créer votre entreprise"
echo "  2. Configurez LDAP dans Admin → Paramètres"
echo "  3. Installez les agents via le token généré"
echo "  4. Optionnel: configurez PyRCA et Neo4j pour l'IA"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo " Sauvegardez ces informations dans un gestionnaire de mots de passe!"
echo ""
