#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT_DIR"

APP_USER="obstack"
APP_ENV="prod"

if [ "$(id -u)" -ne 0 ]; then
    echo "Ce script doit être exécuté en root pour les opérations de déploiement."
    exit 1
fi

if [ ! -d "$ROOT_DIR/.git" ]; then
    echo "Ce répertoire ne semble pas être un dépôt git."
    exit 1
fi

if ! command -v composer >/dev/null 2>&1; then
    echo "Composer introuvable. Installez Composer avant de relancer le script."
    exit 1
fi

if ! command -v php >/dev/null 2>&1; then
    echo "PHP introuvable. Installez PHP avant de relancer le script."
    exit 1
fi

printf "Mise à jour du dépôt git...\n"
git fetch --all --prune

git reset --hard origin/main

printf "Installation des dépendances PHP...\n"
runuser -u "$APP_USER" -- composer install --no-dev --optimize-autoloader --no-interaction

printf "Application des migrations Doctrine...\n"
runuser -u "$APP_USER" -- php bin/console doctrine:migrations:migrate --no-interaction --env=$APP_ENV

printf "Vider et préchauffer le cache Symfony...\n"
runuser -u "$APP_USER" -- php bin/console cache:clear --env=$APP_ENV --no-debug
runuser -u "$APP_USER" -- php bin/console cache:warmup --env=$APP_ENV --no-debug

printf "Déploiement terminé. Si nécessaire, redémarrez PHP-FPM et le serveur web.\n"
