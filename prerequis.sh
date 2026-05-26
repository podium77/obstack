# PHP 8.3 + extensions
apt install php8.3 php8.3-ldap php8.3-intl php8.3-pdo php8.3-xml \
            php8.3-curl php8.3-redis

# Symfony CLI
curl -sS https://get.symfony.com/cli/installer | bash

# Création du projet
mkdir -p ~/Documents/0_projets/Podium/obstack/core/
cd -p ~/Documents/0_projets/Podium/obstack/core/
symfony new obstack --version=8.x --webapp
cd obstack

# Bundles essentiels
composer require symfony/ldap
composer require symfony/messenger          # queue de remédiation
composer require symfony/scheduler          # planification
composer require symfony/ux-turbo
composer require symfony/ux-live-component # métriques temps réel
composer require predis/predis             # Redis pour WebSocket/cache
composer require amphp/ssh2                # SSH vers agents distants
composer require symfony/dotenv


mkdir -p ~/Documents/0_projets/Podium/obstack/core/obstack/{config/{packages,routes},src/{Agent,Controller,Entity,Enum,EventListener,Message,MessageHandler,Repository,Remediation,Scheduler,Security,Service,Twig/{Components,Extension}},templates/{components,dashboard,application,alert,remediation,admin,security},assets/{controllers,styles},migrations,docs}
echo "Structure créée"


