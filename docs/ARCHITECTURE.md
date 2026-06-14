# obstack v1 — Architecture Complète

## Vue d'ensemble

obstack v1 est une plateforme **multi-tenant** d'observabilité pour stacks applicatives
(Tomcat + Oracle/PostgreSQL/MySQL + Java sur Debian/Ubuntu/RedHat/CentOS/Rocky Linux),
avec support natif de **Kubernetes**, détection automatique des technologies, et modules
d'intelligence artificielle (**PyRCA** + **Knowledge Graph**).

---

## Architecture Multi-Tenant

```
┌─────────────────────────────────────────────────────────────────┐
│                    obstack v1 (Debian 12)                       │
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │                   COUCHE PRÉSENTATION                    │   │
│  │  Nginx → PHP-FPM 8.3 → Symfony 8 → Twig                  │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                 │
│  ┌─────────────┐  ┌──────────────┐  ┌────────────────────────┐  │
│  │ PostgreSQL  │  │     Redis    │  │      Supervisor        │  │
│  │ (metadata)  │  │ (queues/cache│  │  ├─ worker-async (x2)  │  │
│  └─────────────┘  └──────────────┘  │  ├─ worker-remediation │  │
│                                     │  ├─ worker-metrics (x2)│  │
│  ┌─────────────┐  ┌──────────────┐  │  └─ scheduler          │  │
│  │    Neo4j    │  │  PyRCA API   │  └────────────────────────┘  │
│  │(Knowledge   │  │ (RCA Python) │                              │
│  │   Graph)    │  └──────────────┘                              │
│  └─────────────┘                                                │
└─────────────────────────────────────────────────────────────────┘
         │
         │ SSH (clé publique) + API REST (token)
         ▼
┌────────────────── ENTREPRISE A ─────────────────────────────────┐
│  Env: PRODUCTION          Env: DÉVELOPPEMENT                    │
│  ┌─────────────────┐      ┌───────────────────┐                 │
│  │ Stack CRM       │      │ Stack CRM-DEV     │                 │
│  │ Tomcat+Oracle   │      │ Tomcat+PostgreSQL │                 │
│  │ Debian 12       │      │ Ubuntu 22.04      │                 │
│  │ VM VMware       │      │ Physique (Dell)   │                 │
│  └─────────────────┘      └───────────────────┘                 │
│                                                                 │
│  Cluster Kubernetes (prod)                                      │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  master-01 (Control Plane)   worker-01   worker-02       │   │
│  │  ├─ kube-apiserver           ├─ pod-crm  ├─ pod-api      │   │
│  │  ├─ etcd                     ├─ pod-db   ├─ pod-cache    │   │
│  │  └─ kube-scheduler           └─ ...      └─ ...          │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

---

## Modèle Multi-Tenant

### Hiérarchie des entités

```
Company (Entreprise/Tenant)
├── licenseKey (unique)
├── slug (unique: "acme-corp-a3f8b2")
├── Environments[]
│   ├── default (créé automatiquement, opérationnel immédiatement)
│   ├── production
│   ├── development
│   └── lab
├── CompanyUsers[]
│   ├── superadmin (1 seul, accès global)
│   ├── user LDAP (illimités)
│   └── user local (max 1 par environnement)
└── Config LDAP (spécifique à cette entreprise)

Environment
├── masterToken (token maître)
├── AgentTokens[] (token = obs_{company_slug}_{env_slug}_{random64})
├── Applications[] (stacks supervisées)
├── EnvironmentUsers[] (droits spécifiques par utilisateur)
└── KubernetesNodes[] (si K8s activé)

AgentToken: obs_acme-corp_prod_a3f8b2...
└── Lié à: company + environment + user (créateur)
```

### Règles d'accès

| Type utilisateur | Règle |
|-----------------|-------|
| **Superadmin** | 1 seul par entreprise, accès total à tous les environnements, non supprimable |
| **LDAP** | Illimités, droits configurables par environnement |
| **Local** | Maximum **1 par environnement** (hors superadmin) |

### Rôles par environnement

| Rôle | Visualiser | Opérer | Administrer | Tokens |
|------|:---------:|:------:|:-----------:|:------:|
| Lecteur (viewer) | ✓ | ✗ | ✗ | ✗ |
| Opérateur | ✓ | ✓ | ✗ | ✗ |
| Admin | ✓ | ✓ | ✓ | ✗ |
| Propriétaire | ✓ | ✓ | ✓ | ✓ |
| Superadmin | ✓ | ✓ | ✓ | ✓ |

---

## Authentification Multi-Tenant

```
POST /login {company_slug, username, password}
        │
        ▼
MultiTenantAuthenticator
        │
        ├─ 1. Trouver Company via slug
        ├─ 2. Si Company.hasLdap():
        │       ├─ Bind LDAP avec ldapBindDn/Password de CETTE entreprise
        │       ├─ Rechercher uid=username dans ldapUserBaseDn
        │       ├─ Bind utilisateur (vérification password)
        │       ├─ Récupérer groupes LDAP
        │       └─ Créer/mettre à jour CompanyUser (type=ldap)
        │
        └─ 3. Sinon: authentification locale (CompanyUser.password bcrypt)
```

Le **slug de l'entreprise** est stocké en session et pré-rempli à la reconnexion.
L'API `/api/company/detect?slug=xxx` permet de détecter et afficher le nom de l'entreprise
en temps réel dans le formulaire de login.

---

## Token Agent — Format et Cycle de vie

```
Format: obs_{company_slug}_{env_slug}_{64_hex_chars}
Exemple: obs_acme-corp-a3f8b2_prod_7f3a8b2c1d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8a9b0c1d2e3f4a5b6c7d8e9f0a

Cycle de vie:
  1. Superadmin crée un token via l'interface → génération automatique
  2. Script bash généré (curl + détection matérielle + installation agent)
  3. Agent s'installe: curl -fsSL https://obs.company.local/api/v1/agent/install/{token} | sudo bash
  4. Agent POST /api/v1/agent/register → enregistrement auto (machine_type, technologies, hardware)
  5. Agent POST /api/v1/agent/metrics toutes les 60s
  6. Agent POST /api/v1/agent/heartbeat toutes les 30s
  7. Token révocable depuis l'interface (isActive=false)
```

---

## Détection Automatique par l'Agent

À l'installation, le script bash détecte:

### Type de machine
| Méthode | Détecte |
|---------|---------|
| `systemd-detect-virt` | KVM, VMware, Hyper-V, Xen, conteneur |
| `dmidecode` | VMware, VirtualBox, Hyper-V, cloud (AWS/GCP/Azure) |
| `/proc/1/environ` | LXC |
| `/.dockerenv` | Docker |
| Aucun hyperviseur → | Bare Metal physique |

### Technologies détectées
- **App Servers**: Tomcat, WildFly, JBoss, WebLogic, WebSphere
- **Web Servers**: Nginx, Apache, HAProxy, Traefik
- **Bases de données**: Oracle, PostgreSQL, MySQL, MariaDB, MongoDB, Redis, Elasticsearch
- **Runtimes**: Java, Node.js, Python, PHP-FPM, .NET
- **Message Brokers**: Kafka, RabbitMQ, ActiveMQ
- **Conteneurs**: Docker (avec liste des containers), Kubernetes (avec rôle node), containerd
- **Monitoring**: Prometheus, Grafana

---

## Support Kubernetes

### Par node

```
KubernetesNode
├── role: master | worker | etcd | ingress (détecté depuis les labels K8s)
├── conditions: Ready, MemoryPressure, DiskPressure, PIDPressure
├── capacity: cpu, memory, pods
├── currentMetrics: cpu_percent, memory_percent (via metrics-server)
└── pods[]: liste complète des pods avec phase, restarts, images
```

### Détection du rôle

```
Labels K8s → node-role.kubernetes.io/control-plane → MASTER
Labels K8s → node-role.kubernetes.io/worker       → WORKER
Labels K8s → node-role.kubernetes.io/etcd         → ETCD
Taints      → key contient "master"/"control-plane"→ MASTER
```

### API Kubernetes supportée

- `/api/v1/nodes` — liste et infos des nodes
- `/api/v1/pods?fieldSelector=spec.nodeName=X` — pods par node
- `/apis/metrics.k8s.io/v1beta1/nodes` — métriques (metrics-server requis)

---

## Intégration PyRCA

```python
# Payload envoyé à l'API PyRCA
{
  "model": "bayesian",          # bayesian | causal | epsilon_diag | random_walk | micro_scope
  "alert": { "metric": "cpu_percent", "value": 95.2, "severity": "critical" },
  "application": { "name": "CRM-Portal", "technologies": ["tomcat", "oracle"] },
  "time_series": [
    { "timestamp": 1700000000, "cpu_percent": 45.2, "memory_percent": 62.1, ... },
    { "timestamp": 1700000060, "cpu_percent": 78.9, "memory_percent": 65.3, ... },
    ...
  ]
}

# Réponse PyRCA
{
  "root_causes": [
    { "component": "oracle_db", "score": 0.89, "reason": "Requêtes SQL lentes détectées" },
    { "component": "tomcat_thread_pool", "score": 0.72, "reason": "Saturation du pool" }
  ],
  "confidence": 0.87,
  "explanation": "La hausse CPU est causée par des requêtes Oracle non optimisées...",
  "causal_graph": { ... }
}
```

---

## Intégration Knowledge Graph (Neo4j)

### Graphe de dépendances

```cypher
// Noeuds créés automatiquement
(:Application {name: "CRM-Portal", environment: "prod"})
(:Technology  {type: "tomcat", version: "9.0.65"})
(:Database    {type: "oracle"})
(:KubernetesNode {name: "worker-01", role: "worker"})
(:Pod         {name: "crm-pod-xyz", namespace: "default"})
(:Alert       {severity: "critical", metric: "cpu_percent"})
(:Remediation {action: "memory_free", success: true})

// Relations
(app)-[:HAS_TECHNOLOGY]->(tech)
(app)-[:CONNECTS_TO]->(db)
(pod)-[:RUNS_ON]->(node)
(app)-[:HAS_ALERT]->(alert)
(app)-[:WAS_REMEDIATED]->(remediation)
```

### Requêtes utiles

```cypher
-- Points de défaillance uniques (SPOF)
MATCH (n)
WHERE COUNT {()-[:DEPENDS_ON|CONNECTS_TO]->(n)} >= 2
RETURN n ORDER BY dependencies DESC

-- Propagation d'incident
MATCH (a:Application)-[:CONNECTS_TO]->(db:Database)<-[:CONNECTS_TO]-(b:Application)
WHERE a.name = "CRM-Portal"
RETURN b.name AS impacted_service
```

---

## Déploiement

### Installation rapide (Docker)

```bash
# Cloner et démarrer
git clone https://github.com/company/obstack.git
cd obstack/docker

# Stack de base
docker compose up -d

# Avec Knowledge Graph Neo4j
docker compose --profile kg up -d

# Avec PyRCA
docker compose --profile rca up -d

# Migrations
docker compose exec app php bin/console doctrine:migrations:migrate

# S'inscrire: http://localhost:8080/register
```

### Installation Debian 12 (production)

```bash
sudo bash install.sh
# Puis s'inscrire sur https://obstack.company.local/register
```

### Installer un agent sur un serveur supervisé

```bash
# Récupérer le token depuis l'interface obstack → Environnement → Tokens
curl -fsSL https://obstack.company.local/api/v1/agent/install/obs_acme_prod_xxx | sudo bash
```

---

## Structure des fichiers

```
obstack/
├── src/
│   ├── Entity/          Application, Company, Environment, CompanyUser,
│   │                    EnvironmentUser, AgentToken,
│   │                    KubernetesNode, KubernetesPod,
│   │                    MetricSnapshot, RemediationLog, RemediationPolicy, Alert
│   ├── Enum/            OsType, DbType, MachineType, TechnologyType,
│   │                    EnvironmentType, NodeRole, UserEnvironmentRole,
│   │                    RemediationAction, AlertSeverity, TriggerMetric
│   ├── Controller/      SecurityController, CompanyRegistrationController,
│   │                    EnvironmentController, AgentApiController,
│   │                    KubernetesController, DashboardController,
│   │                    ApplicationController, RemediationController,
│   │                    AlertController, AdminController
│   ├── Security/        MultiTenantAuthenticator
│   ├── Service/         CompanyProvisioningService, TenantContext,
│   │                    AgentInstallScriptGenerator, NotificationService
│   ├── Agent/           MetricCollector, SshClient, SshConnection
│   ├── Kubernetes/      KubernetesCollector
│   ├── RCA/             PyRcaService, KnowledgeGraphService, RcaResult
│   ├── Message/         CollectMetricsMessage, RemediationJobMessage, ...
│   ├── MessageHandler/  CollectMetricsHandler, RemediationJobHandler, ...
│   └── Scheduler/       MetricSchedule
├── templates/
│   ├── company/         register.html.twig, register_success.html.twig
│   ├── security/        login.html.twig
│   ├── environment/     index, show, form, users
│   ├── kubernetes/      dashboard, node_detail, not_configured
│   ├── dashboard/       index (tableau de bord global)
│   ├── application/     index, show, form
│   ├── remediation/     index, log_show, policy_form
│   └── alert/           index
├── migrations/          Version20240201000001.php
├── docker/              Dockerfile, docker-compose.yml, nginx.conf
├── docs/                ARCHITECTURE.md
└── .env
```
