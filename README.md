# obstack — Platform d'observabilité multi-tenant

![Status](https://img.shields.io/badge/status-beta-yellow)
![License](https://img.shields.io/badge/license-MIT-blue)

**obstack** est une plateforme d'observabilité et de monitoring distribuée, multi-tenant, avec support natif pour Kubernetes, auto-remédiation intelligente et analyse de root cause (PyRCA) basée sur un graphe de connaissances.

## 🎯 Caractéristiques principales

- **Multi-tenant** : isolation complète par entreprise/environnement
- **Agents distribués** : déploiement léger avec autodétection matérielle
- **Kubernetes-ready** : monitoring natif de clusters K8s
- **Auto-remédiation** : correction automatique des problèmes détectés
- **PyRCA** : analyse intelligente des causes racines
- **Knowledge Graph** : cartographie dynamique de l'infrastructure
- **API REST** : intégration facile avec écosystèmes existants
- **Webhooks** : notifications Slack/Teams/custom

---

## 📋 Prérequis

### Système

- **OS** : Debian 12+, Ubuntu 20.04+, ou RHEL 8+
- **RAM** : 4GB minimum (8GB recommandé)
- **Disque** : 20GB minimum SSD
- **CPU** : 2 cores minimum

### Logiciels

- **PHP** : 8.2+
- **PostgreSQL** : 13+
- **Redis** : 6+
- **Nginx** : 1.20+
- **Docker/Docker Compose** : 20.10+ (optionnel, pour déploiement conteneurisé)
- **Git** : pour cloner le dépôt

### Optionnel

- **GPG** : pour vérifier les signatures d'agents
- **Kubernetes** : pour monitoring K8s natif
- **Python 3** : pour collecteurs additionnels

---

## 🚀 Installation

### Option 1 : Développement (local)

#### 1. Cloner le dépôt

```bash
git clone https://github.com/your-org/obstack.git
cd obstack
```

#### 2. Installer les dépendances

```bash
make install
# ou manuellement :
composer install
cp -n .env .env.local
```

#### 3. Configurer l'environnement

Éditer `.env.local` :

```env
APP_ENV=dev
APP_BASE_URL=http://localhost:8001
DATABASE_URL="postgresql://obstack:password@127.0.0.1:5432/obstack"
REDIS_URL=redis://127.0.0.1:6379
MESSENGER_TRANSPORT_DSN=redis://127.0.0.1:6379/messages

# Agent installation (optionnel)
OBS_AGENT_ARTIFACT_BASE_URL=https://releases.example.com/obsagent
OBS_AGENT_PUBKEY_URL=https://releases.example.com/obsagent.pub
OBS_AGENT_PUBKEY_FINGERPRINT=ABC123...
```

#### 4. Initialiser la base de données

```bash
make migrate
```

#### 5. Démarrer le serveur

```bash
make dev
# Le serveur est accessible sur https://localhost:8000
```

### Option 2 : Production avec Docker

#### 1. Cloner et configurer

```bash
git clone https://github.com/your-org/obstack.git
cd obstack/docker
```

#### 2. Configurer docker-compose

Éditer `docker-compose.yml` ou créer `.env` :

```env
APP_ENV=prod
APP_BASE_URL=https://obstack.company.local
DATABASE_URL=postgresql://obstack:secure_password@postgres:5432/obstack_prod
REDIS_URL=redis://redis:6379
MESSENGER_TRANSPORT_DSN=redis://redis:6379/messages
```

#### 3. Démarrer les conteneurs

```bash
docker-compose up -d

# Initialiser la base de données
docker-compose exec app php bin/console doctrine:migrations:migrate --no-interaction
```

#### 4. Accéder à la plateforme

- **URL** : http://localhost:8080 (ou votre domaine)
- **Identifiants** : définis lors de la création du premier utilisateur

### Option 3 : Installation native (Debian/Ubuntu)

```bash
sudo bash install.sh
# Le script gère tous les prérequis et la configuration
```

---

## ⚙️ Configuration

### Fichiers principaux

- **`.env`** : variables d'environnement (base de données, Redis, etc.)
- **`config/services.yaml`** : configuration des services Symfony
- **`config/packages/security.yaml`** : authentification LDAP/OAuth2

### Variables d'environnement essentielles

| Variable | Description | Défaut |
|----------|-------------|--------|
| `APP_ENV` | Environnement (dev/prod) | `dev` |
| `APP_SECRET` | Clé secrète Symfony | (générée) |
| `APP_BASE_URL` | URL de la plateforme | `http://localhost:8001` |
| `DATABASE_URL` | Connexion PostgreSQL | - |
| `REDIS_URL` | Connexion Redis | `redis://127.0.0.1:6379` |
| `MESSENGER_TRANSPORT_DSN` | File d'attente Messenger | Redis URL |
| `OBS_AGENT_ARTIFACT_BASE_URL` | URL des artefacts agents | - |
| `OBS_AGENT_PUBKEY_URL` | URL de la clé publique GPG | - |
| `OBS_AGENT_PUBKEY_FINGERPRINT` | Fingerprint attendu | - |

### LDAP (optionnel)

Pour intégrer un LDAP ou Active Directory :

```env
LDAP_HOST=ldap.company.local
LDAP_PORT=389
LDAP_BASE_DN=dc=company,dc=local
LDAP_BIND_DN=cn=reader,dc=company,dc=local
LDAP_BIND_PASSWORD=password
LDAP_USER_BASE_DN=ou=users,dc=company,dc=local
LDAP_ADMIN_GROUP=cn=obstack-admins,ou=groups,dc=company,dc=local
```

### Webhooks (Slack, Teams)

```env
WEBHOOK_SLACK_URL=https://hooks.slack.com/services/T00000000/B00000000/XXXXXXXXXXXXXXXXXXXX
WEBHOOK_TEAMS_URL=https://outlook.webhook.office.com/webhookb2/...
```

---

## 📦 Déploiement d'agents

### Générer un token d'agent

1. Accéder à **Admin > Agents**
2. Cliquer sur **Nouveau token**
3. Sélectionner l'entreprise et l'environnement
4. Choisir les modules activés (Prometheus, OpenTelemetry, etc.)
5. Générer et télécharger le script d'installation

### Télécharger le script d'installation

Le script peut être téléchargé de plusieurs manières :

```bash
# Directement depuis la plateforme
curl -fsSL https://obstack.company.local/api/v1/agent/install/{TOKEN} -o install.sh

# Avec options personnalisées
curl -fsSL https://obstack.company.local/api/v1/agent/install/{TOKEN} | bash \
  --install-dir /opt/obsagent \
  --user obsagent-prod \
  --artifact-url https://releases.company.local/obsagent.tar.gz
```

### Options d'installation

Le script supporte les paramètres suivants :

```
--install-dir <path>        Répertoire d'installation (défaut: /opt/obstack-agent)
--user <user>              Utilisateur propriétaire des fichiers
--artifact-url <url>       URL du binaire obsagent
--sig-url <url>            URL de la signature GPG
--pubkey-url <url>         URL de la clé publique
--pubkey-file <path>       Fichier local de clé publique
--pubkey-fingerprint <fp>  Empreinte publique attendue
```

### Exemples d'installation

#### Installation simple (root)

```bash
curl -fsSL https://obstack.company.local/api/v1/agent/install/{TOKEN} | sudo bash
```

#### Installation non-root

```bash
curl -fsSL https://obstack.company.local/api/v1/agent/install/{TOKEN} | bash \
  --install-dir /home/monitoring/obsagent \
  --user monitoring
```

#### Avec répertoire personnalisé et clé publique

```bash
curl -fsSL https://obstack.company.local/api/v1/agent/install/{TOKEN} | bash \
  --install-dir /var/obstack/agent \
  --user obstack-agent \
  --pubkey-fingerprint "ABC123DEF456..."
```

---

## 📊 Utilisation

### Interface Web

#### Dashboard

- Vue d'ensemble des alertes actives
- État des agents et collecteurs
- Métriques en temps réel
- Événements récents

#### Administration

- **Entreprises** : création et gestion multi-tenant
- **Environnements** : organisation par prod/staging/dev
- **Applications** : serveurs et services monitorés
- **Agents** : tokens et configuration
- **Alertes** : règles et seuils
- **Utilisateurs** : gestion des accès

#### Monitoring

- **Métriques** : CPU, RAM, disque, réseau
- **Logs** : collecte et indexation
- **Traces** : traçage distribué (OpenTelemetry)
- **Événements** : timeline d'infrastructure
- **Graphe de connaissances** : cartographie des dépendances

### API REST

#### Authentification

```bash
# Bearer token (agent)
curl -H "Authorization: Bearer {TOKEN}" https://obstack.company.local/api/v1/agent/metrics
```

#### Principaux endpoints

| Endpoint | Méthode | Description |
|----------|---------|-------------|
| `/api/v1/agent/install/{token}` | GET | Télécharger le script d'installation |
| `/api/v1/agent/register` | POST | Enregistrer l'agent |
| `/api/v1/agent/metrics` | POST | Envoyer des métriques |
| `/api/v1/agent/heartbeat` | POST | Signal de vie (alive) |
| `/api/v1/alerts` | GET/POST | Gestion des alertes |
| `/api/v1/applications` | GET/POST | Gestion des applications |

#### Exemple : Envoyer des métriques

```bash
curl -X POST https://obstack.company.local/api/v1/agent/metrics \
  -H "Authorization: Bearer {TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "timestamp": 1717353600,
    "cpu_percent": 42.5,
    "memory": {"used_mb": 2048, "total_mb": 8192},
    "disks": [{"device": "/dev/sda1", "used_gb": 50, "total_gb": 100}]
  }'
```

### CLI

#### Commandes principales

```bash
# Lister les environnements
php bin/console app:environment:list

# Créer une entreprise
php bin/console app:company:create --name "Acme Corp" --slug "acme"

# Générer un token d'agent
php bin/console app:agent:generate-token --company=acme --environment=prod

# Lancer les migrations
php bin/console doctrine:migrations:migrate

# Vider le cache
php bin/console cache:clear
```

---

## 🏗️ Architecture

### Composants principaux

```
┌─────────────────────────────────────────────────┐
│         obstack Platform (Symfony + Twig)       │
│  ┌──────────────────────────────────────────┐   │
│  │  Symfony Kernel, Services, Security      │   │
│  │  - TenantContext (multi-tenant)          │   │
│  │  - AgentApiController (REST API)         │   │
│  │  - MetricCollector (collecte SSH)        │   │
│  │  - PyRcaService (analyse RCA)            │   │
│  │  - KnowledgeGraphService (cartographie)  │   │
│  └──────────────────────────────────────────┘   │
├─────────────────────────────────────────────────┤
│              Data Storage Layer                  │
│  ┌──────────────┐    ┌───────────────────────┐ │
│  │  PostgreSQL  │    │      Redis            │ │
│  │ (DB + Docs)  │    │ (Cache + Messenger)   │ │
│  └──────────────┘    └───────────────────────┘ │
└─────────────────────────────────────────────────┘
         ↑                           ↑
    ┌────┴─────────────────────────┴──────┐
    │                                      │
┌───────────────┐                 ┌──────────────┐
│ Agents (Rust) │                 │  CLI Workers │
│   (obsagent)  │                 │  (Messenger) │
└───────────────┘                 └──────────────┘
```

### Flux de données

1. **Agent** collecte métriques/logs sur les serveurs
2. Agent envoie à `/api/v1/agent/metrics`
3. Platform persiste dans PostgreSQL
4. Messenger (async workers) traite les tâches longues
5. UI affiche les données en temps réel
6. PyRCA analyse et détecte les causes racines
7. Remédiation applique les corrections automatiques

---

## 🔧 Maintenance

### Sauvegardes

#### Base de données

```bash
# Sauvegarde manuelle
pg_dump -U obstack -h localhost obstack > obstack_backup_$(date +%Y%m%d).sql

# Restauration
psql -U obstack -h localhost obstack < obstack_backup_20240601.sql
```

#### Docker

```bash
# Sauvegarder les volumes
docker-compose exec postgres pg_dump -U obstack obstack > backup.sql
```

### Logs

#### Serveur (Symfony)

```bash
# Développement
tail -f var/log/dev.log

# Production
tail -f var/log/prod.log | grep ERROR
```

#### Agents

```bash
# Consulter les logs d'agent
tail -f /var/log/obstack-agent-install.log
tail -f /opt/obstack-agent/logs/agent.log
```

#### Docker

```bash
docker-compose logs -f app
docker-compose logs -f nginx
docker-compose logs -f worker-async
```

### Mises à jour

```bash
# Synchroniser les dépendances
composer install --no-dev

# Appliquer les migrations
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

# Vider les caches
php bin/console cache:clear --env=prod

# Redémarrer les services
sudo systemctl restart obstack
```

---

## 🐛 Troubleshooting

### Agent non enregistré

1. Vérifier la connexion réseau : `curl -I https://obstack.company.local`
2. Vérifier le token : `/api/v1/agent/install/{TOKEN}` retourne 403?
3. Consulter les logs : `/opt/obstack-agent/logs/agent.log`
4. Réactiver le service : `systemctl restart obstack-agent.timer`

### Métriques manquantes

1. Vérifier que l'agent a les permissions SSH (si collecte distante)
2. Vérifier la clé SSH : `ssh -i ~/.ssh/id_rsa monitoring@target-server`
3. Redémarrer le collecteur : `php bin/console app:metric:collect`

### Base de données pleine

```bash
# Nettoyer les anciennes métriques (> 90 jours)
php bin/console app:metric:cleanup --retention-days=90

# Optimiser PostgreSQL
vacuumdb -U obstack obstack
```

### Messenger bloqué

```bash
# Inspecter la file d'attente
php bin/console messenger:failed:show

# Rejouer les messages échoués
php bin/console messenger:failed:retry
```

---

## 📚 Documentation supplémentaire

- [Architecture détaillée](docs/ARCHITECTURE.md)
- [Spécification des agents](docs/AGENTS.md)
- [Guide d'intégration API](docs/API_INTEGRATION.md)
- [Configuration LDAP/OAuth2](docs/AUTHENTICATION.md)
- [Dépannage avancé](docs/TROUBLESHOOTING.md)

---

## 🤝 Contribution

Les contributions sont bienvenues! Veuillez consulter [CONTRIBUTING.md](CONTRIBUTING.md) pour les directives.

## 📄 Licence

Ce projet est sous licence MIT — voir [LICENSE](LICENSE) pour les détails.

## 📞 Support

- **Issues** : https://github.com/your-org/obstack/issues
- **Documentation** : https://docs.obstack.io
- **Email** : support@obstack.io
