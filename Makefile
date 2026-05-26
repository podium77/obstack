# obstack v1 — Makefile
# Usage: make [target]

.PHONY: help install dev prod test migrate assets workers logs clean k8s-sync

# ─── Couleurs ─────────────────────────────────────────────────────────────────
GREEN  = \033[0;32m
YELLOW = \033[1;33m
CYAN   = \033[0;36m
NC     = \033[0m

help: ## Affiche cette aide
	@echo ""
	@echo "$(CYAN)obstack v1 — Commandes disponibles$(NC)"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  $(GREEN)%-25s$(NC) %s\n", $$1, $$2}'
	@echo ""

# ─── Installation ─────────────────────────────────────────────────────────────
install: ## Installation complète (développement)
	@echo "$(CYAN)Installation des dépendances...$(NC)"
	composer install
	cp -n .env .env.local 2>/dev/null || true
	@echo "$(YELLOW)Éditez .env.local puis lancez: make migrate$(NC)"

dev: ## Démarrer le serveur de développement
	symfony server:start --daemon
	@echo "$(GREEN)Serveur démarré sur https://localhost:8000$(NC)"

stop: ## Arrêter le serveur de développement
	symfony server:stop

# ─── Base de données ──────────────────────────────────────────────────────────
migrate: ## Exécuter les migrations
	php bin/console doctrine:migrations:migrate --no-interaction --env=dev
	@echo "$(GREEN)Migrations appliquées$(NC)"

migrate-prod: ## Migrations en production
	php bin/console doctrine:migrations:migrate --no-interaction --env=prod

migrate-diff: ## Générer une migration depuis les changements d'entité
	php bin/console doctrine:migrations:diff --env=dev

db-reset: ## Réinitialiser la base de données (DEV seulement!)
	@read -p "Confirmer la réinitialisation de la BDD? [y/N] " yn; \
	if [ "$$yn" = "y" ]; then \
		php bin/console doctrine:database:drop --force --env=dev; \
		php bin/console doctrine:database:create --env=dev; \
		php bin/console doctrine:migrations:migrate --no-interaction --env=dev; \
		echo "$(GREEN)Base de données réinitialisée$(NC)"; \
	fi

# ─── Workers & Scheduler ─────────────────────────────────────────────────────
workers: ## Démarrer tous les workers (développement)
	@echo "$(CYAN)Démarrage des workers...$(NC)"
	@php bin/console messenger:consume async --env=dev &
	@php bin/console messenger:consume remediation --env=dev &
	@php bin/console messenger:consume metrics --env=dev &
	@php bin/console messenger:consume kubernetes --env=dev &
	@php bin/console scheduler:run --env=dev &
	@echo "$(GREEN)Workers démarrés en arrière-plan$(NC)"

worker-metrics: ## Worker métriques uniquement
	php bin/console messenger:consume metrics --time-limit=3600 --env=dev -vv

worker-remediation: ## Worker remédiation uniquement
	php bin/console messenger:consume remediation --time-limit=3600 --env=dev -vv

scheduler: ## Démarrer le scheduler
	php bin/console scheduler:run --env=dev -vv

# ─── Tests ────────────────────────────────────────────────────────────────────
test: ## Lancer tous les tests
	php bin/phpunit --testdox

test-unit: ## Tests unitaires uniquement
	php bin/phpunit tests/ --testdox --exclude-group integration

test-cover: ## Tests avec couverture de code
	php bin/phpunit --coverage-html var/coverage

# ─── Assets & Cache ───────────────────────────────────────────────────────────
assets: ## Installer les assets
	php bin/console assets:install public --env=dev

cache-clear: ## Vider le cache
	php bin/console cache:clear --env=dev
	php bin/console cache:warmup --env=dev

cache-prod: ## Cache de production
	php bin/console cache:clear --env=prod --no-debug
	php bin/console cache:warmup --env=prod --no-debug

# ─── Logs ─────────────────────────────────────────────────────────────────────
logs: ## Afficher les logs en temps réel
	tail -f var/log/dev.log

logs-prod: ## Logs de production
	tail -f /var/log/obstack/*.log

# ─── Docker ───────────────────────────────────────────────────────────────────
docker-up: ## Démarrer la stack Docker
	cd docker && docker compose up -d
	@echo "$(GREEN)Stack démarrée → http://localhost:8080$(NC)"

docker-up-full: ## Stack Docker avec Neo4j et PyRCA
	cd docker && docker compose --profile kg --profile rca up -d

docker-down: ## Arrêter la stack Docker
	cd docker && docker compose down

docker-logs: ## Logs Docker
	cd docker && docker compose logs -f --tail=50

docker-migrate: ## Migrations dans Docker
	cd docker && docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction

docker-shell: ## Shell dans le container app
	cd docker && docker compose exec app bash

docker-build: ## Rebuild de l'image Docker
	cd docker && docker compose build --no-cache

# ─── Kubernetes ───────────────────────────────────────────────────────────────
k8s-sync: ## Déclencher une sync K8s manuelle (nécessite ENV_ID=xxx)
	@if [ -z "$(ENV_ID)" ]; then echo "Usage: make k8s-sync ENV_ID=1"; exit 1; fi
	php bin/console messenger:dispatch App\\Message\\SyncKubernetesMessage --env=dev \
		-- --arg='{"environmentId": $(ENV_ID)}'

# ─── Maintenance ──────────────────────────────────────────────────────────────
clean: ## Nettoyer les fichiers temporaires
	rm -rf var/cache/* var/log/*.log
	@echo "$(GREEN)Nettoyage effectué$(NC)"

lint: ## Vérifier la syntaxe PHP
	php bin/console lint:twig templates/
	php bin/console lint:yaml config/
	php -l src/**/*.php

analyse: ## Analyse statique PHPStan (si installé)
	@if command -v phpstan >/dev/null; then \
		phpstan analyse src/ --level=5; \
	else \
		echo "$(YELLOW)PHPStan non installé. Installez avec: composer require --dev phpstan/phpstan$(NC)"; \
	fi

detect-agent: ## Tester la détection des technologies localement
	python3 agent/scripts/detect_technologies.py --pretty

collect-metrics: ## Tester la collecte des métriques localement
	python3 agent/scripts/collect_metrics.py --no-send --pretty

check-all: lint test ## Lint + Tests complets

# ─── Déploiement ──────────────────────────────────────────────────────────────
deploy: ## Déployer en production (à adapter)
	@echo "$(CYAN)Déploiement en production...$(NC)"
	git pull origin main
	composer install --no-dev --optimize-autoloader
	make cache-prod
	make migrate-prod
	sudo supervisorctl restart obstack:*
	@echo "$(GREEN)Déploiement terminé$(NC)"
