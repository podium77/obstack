#!/usr/bin/env bash
# =============================================================================
# Obstack — Script d'installation complète sur Debian 12
# Usage: sudo bash install.sh
# =============================================================================
set -euo pipefail

APP_USER="obstack"
APP_DIR="/var/www/obstack"
PHP_VER="8.3"
DB_NAME="obstack"
DB_USER="obstack"
DB_PASS="$(openssl rand -hex 16)"

echo "======================================================"
echo "  Obstack — Installation (Debian 12)"
echo "======================================================"

# --- Prérequis système ---
apt-get update -qq
apt-get install -y --no-install-recommends \
    curl git unzip ca-certificates gnupg lsb-release \
    supervisor redis-server postgresql postgresql-contrib \
    php${PHP_VER} php${PHP_VER}-fpm php${PHP_VER}-cli \
    php${PHP_VER}-pgsql php${PHP_VER}-ldap php${PHP_VER}-intl \
    php${PHP_VER}-xml php${PHP_VER}-curl php${PHP_VER}-redis \
    php${PHP_VER}-mbstring php${PHP_VER}-zip php${PHP_VER}-bcmath \
    libphp-ssh2 php-ssh2 \
    nginx openssh-client

echo "[OK] Paquets système installés"

# --- Composer ---
if ! command -v composer &>/dev/null; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi
echo "[OK] Composer installé"

# --- Symfony CLI ---
curl -sS https://get.symfony.com/cli/installer | bash
mv "$HOME/.symfony5/bin/symfony" /usr/local/bin/symfony 2>/dev/null || true
echo "[OK] Symfony CLI installé"

# --- Utilisateur applicatif ---
if ! id "$APP_USER" &>/dev/null; then
    useradd -r -m -d /var/lib/obstack -s /bin/bash "$APP_USER"
fi

# Génération clé SSH pour les connexions agents
if [ ! -f "/var/lib/obstack/.ssh/id_rsa" ]; then
    sudo -u "$APP_USER" mkdir -p /var/lib/obstack/.ssh
    sudo -u "$APP_USER" ssh-keygen -t rsa -b 4096 -N '' \
        -f /var/lib/obstack/.ssh/id_rsa \
        -C "obstack-agent@$(hostname)"
    chmod 700 /var/lib/obstack/.ssh
    chmod 600 /var/lib/obstack/.ssh/id_rsa
fi

echo "[OK] Utilisateur $APP_USER créé"
echo ""
echo "===> Clé publique SSH à déployer sur les serveurs cibles:"
cat /var/lib/obstack/.ssh/id_rsa.pub
echo ""

# --- PostgreSQL ---
systemctl start postgresql
sudo -u postgres psql <<SQL
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = '${DB_USER}') THEN
        CREATE USER ${DB_USER} WITH PASSWORD '${DB_PASS}';
    END IF;
END
\$\$;
CREATE DATABASE IF NOT EXISTS ${DB_NAME} OWNER ${DB_USER};
GRANT ALL PRIVILEGES ON DATABASE ${DB_NAME} TO ${DB_USER};
SQL
echo "[OK] PostgreSQL configuré (db: $DB_NAME, user: $DB_USER)"

# --- Redis ---
systemctl enable --now redis-server
echo "[OK] Redis démarré"

# --- Application Symfony ---
if [ ! -d "$APP_DIR" ]; then
    mkdir -p "$APP_DIR"
fi

# Copier les fichiers (supposés dans le répertoire courant)
cp -r . "$APP_DIR/"
chown -R "$APP_USER:$APP_USER" "$APP_DIR"

# Fichier .env.local
cat > "$APP_DIR/.env.local" <<ENV
APP_ENV=prod
APP_SECRET=$(openssl rand -hex 32)
DATABASE_URL="postgresql://${DB_USER}:${DB_PASS}@127.0.0.1:5432/${DB_NAME}?serverVersion=16&charset=utf8"
MESSENGER_TRANSPORT_DSN=redis://127.0.0.1:6379/messages
REDIS_URL=redis://127.0.0.1:6379
LDAP_HOST=openldap.company.local
LDAP_PORT=389
LDAP_BASE_DN=dc=company,dc=local
LDAP_BIND_DN=cn=ldap-reader,dc=company,dc=local
LDAP_BIND_PASSWORD=CHANGE_ME
LDAP_USER_BASE_DN=ou=users,dc=company,dc=local
LDAP_GROUP_BASE_DN=ou=groups,dc=company,dc=local
MAILER_DSN=smtp://localhost:25
MAIL_FROM=obstack@company.local
MAIL_ADMIN=admin@company.local
ENV

echo "[OK] .env.local généré"

# Installation dépendances PHP
cd "$APP_DIR"
sudo -u "$APP_USER" composer install --no-dev --optimize-autoloader --no-interaction

# Migrations BDD
sudo -u "$APP_USER" php bin/console doctrine:migrations:migrate --no-interaction --env=prod
sudo -u "$APP_USER" php bin/console cache:warmup --env=prod

echo "[OK] Application Symfony configurée"

# --- Nginx ---
cat > /etc/nginx/sites-available/obstack <<'NGINX'
server {
    listen 80;
    server_name obstack.company.local;
    root /var/www/obstack/public;
    index index.php;

    client_max_body_size 50M;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2?)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    add_header X-Frame-Options SAMEORIGIN;
    add_header X-Content-Type-Options nosniff;
    add_header X-XSS-Protection "1; mode=block";
}
NGINX

ln -sf /etc/nginx/sites-available/obstack /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
echo "[OK] Nginx configuré"

# --- Supervisor (workers Messenger + Scheduler) ---
cat > /etc/supervisor/conf.d/obstack.conf <<'SUP'
[program:obstack-messenger-async]
command=php /var/www/obstack/bin/console messenger:consume async --time-limit=3600 --env=prod
user=obstack
numprocs=2
autostart=true
autorestart=true
startsecs=5
stdout_logfile=/var/log/obstack/messenger-async.log
stderr_logfile=/var/log/obstack/messenger-async-err.log

[program:obstack-messenger-remediation]
command=php /var/www/obstack/bin/console messenger:consume remediation --time-limit=3600 --env=prod
user=obstack
numprocs=1
autostart=true
autorestart=true
startsecs=5
stdout_logfile=/var/log/obstack/messenger-remediation.log
stderr_logfile=/var/log/obstack/messenger-remediation-err.log

[program:obstack-messenger-metrics]
command=php /var/www/obstack/bin/console messenger:consume metrics --time-limit=3600 --env=prod
user=obstack
numprocs=2
autostart=true
autorestart=true
startsecs=5
stdout_logfile=/var/log/obstack/messenger-metrics.log
stderr_logfile=/var/log/obstack/messenger-metrics-err.log

[program:obstack-scheduler]
command=php /var/www/obstack/bin/console scheduler:run --env=prod
user=obstack
numprocs=1
autostart=true
autorestart=true
startsecs=5
stdout_logfile=/var/log/obstack/scheduler.log
stderr_logfile=/var/log/obstack/scheduler-err.log

[group:obstack]
programs=obstack-messenger-async,obstack-messenger-remediation,obstack-messenger-metrics,obstack-scheduler
SUP

mkdir -p /var/log/obstack
chown -R "$APP_USER:$APP_USER" /var/log/obstack

supervisorctl reread
supervisorctl update
supervisorctl start obstack:*
echo "[OK] Supervisor configuré et workers démarrés"

# --- Logrotate ---
cat > /etc/logrotate.d/obstack <<'LR'
/var/log/obstack/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 obstack obstack
}
LR

# --- Sudo pour l'utilisateur applicatif ---
cat > /etc/sudoers.d/obstack <<'SUDO'
# Obstack — accès sudo sans mot de passe pour les commandes d'administration
obstack ALL=(ALL) NOPASSWD: \
    /bin/systemctl start *, \
    /bin/systemctl stop *, \
    /bin/systemctl restart *, \
    /bin/systemctl is-active *, \
    /sbin/shutdown *, \
    /usr/bin/find *, \
    /bin/rm -f *, \
    /bin/sync, \
    /bin/echo * > /proc/sys/vm/drop_caches, \
    /sbin/swapon *, \
    /sbin/swapoff *, \
    /usr/bin/at *
SUDO
chmod 440 /etc/sudoers.d/obstack

echo ""
echo "======================================================"
echo "  Installation terminée !"
echo "======================================================"
echo ""
echo "  URL:         http://obstack.company.local"
echo "  DB password: $DB_PASS (sauvegardez-le dans .env.local)"
echo ""
echo "  Prochaines étapes:"
echo "  1. Configurer .env.local avec vos paramètres LDAP"
echo "  2. Déployer la clé SSH publique sur les serveurs cibles"
echo "  3. Ajouter la première application via l'interface web"
echo ""
echo "  Clé SSH publique de l'agent:"
cat /var/lib/obstack/.ssh/id_rsa.pub
