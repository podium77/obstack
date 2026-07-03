# Intégration IA Centralisée - Guide d'Architecture

Ce document spécifie les principes d'architecture et les contraintes pour l'intégration d'IA dans Obstack.

## Table des matières

- [Principes fondamentaux](#principes-fondamentaux)
- [Modes de fonctionnement](#modes-de-fonctionnement)
- [Architecture cible](#architecture-cible)
- [Contraintes techniques](#contraintes-techniques)
- [Roadmap d'implémentation](#roadmap-dimplémentation)
- [Guidelines de développement](#guidelines-de-développement)

---

## Principes fondamentaux

### 1. IA Centralisée

L'intelligence artificielle **ne doit jamais** être déployée sur :

- Les agents d'observabilité (`obsagent`)
- Les hôtes/machines surveillées
- Les conteneurs d'application

**Raison :** Mantenir une faible empreinte mémoire et CPU sur les systèmes de production.

### 2. Agents légers

Les agents d'observabilité se limitent strictement à :

```
Agent d'observabilité:
├── Collecte des métriques (Prometheus)
├── Collecte des logs (Syslog/JSON)
├── Collecte des traces (OpenTelemetry)
├── Enrichissement minimal (tags, labels)
└── Transmission HTTP/gRPC vers Obstack Platform
```

**Jamais :**
- Exécution de modèles LLM
- Installation d'Ollama
- Déploiement de Neo4j
- Environnements Python spécialisés pour l'IA

### 3. Découplage complet

La communication observabilité ↔ IA passe **uniquement par l'API** :

```
Agents                  Obstack Platform            Infrastructure IA
┌────────┐             ┌──────────────┐            ┌──────────────┐
│Collecte│──HTTP──────→│   REST API   │────HTTP───→│  Ollama API  │
│Metrics │             │  Documented  │            │  Port 11434  │
└────────┘             └──────────────┘            └──────────────┘
```

Cela permet :
- Remplacement du fournisseur IA
- Mise à jour indépendante des modèles
- Évolution sans couplage

---

## Modes de fonctionnement

### Mode 1 : IA Existante

**Cas d'usage :** L'administrateur dispose déjà d'une infrastructure Ollama.

**Configuration manuelle :**

```env
# .env.local
AI_MODE=existing
AI_PROVIDER=ollama
AI_API_URL=http://ollama.company.local:11434
AI_API_KEY=optional-api-key
AI_MODEL=qwen3:8b
AI_TIMEOUT_SECONDS=30
```

**Étapes d'implémentation :**

1. **Service `OllamaClient.php`**
   ```php
   class OllamaClient {
       public function testConnection(): bool { }
       public function listModels(): array { }
       public function chat(string $prompt, string $model): string { }
       public function generate(string $prompt, array $options): GenerateResponse { }
   }
   ```

2. **Service `AiConfigurationValidator.php`**
   ```php
   class AiConfigurationValidator {
       public function validateMode1(array $config): ValidationResult { }
       // Vérifie :
       // - Accessibilité API
       // - Disponibilité du modèle
       // - Authentification si clé API
   }
   ```

3. **Form `AIMode1ConfigurationType.php`**
   ```php
   class AIMode1ConfigurationType extends AbstractType {
       // Champs :
       // - API URL (text, required)
       // - API Key (password, optional)
       // - Model choice (choice, qwen3:4b/8b)
       // - Bouton "Test Connection"
   }
   ```

4. **Controller `Admin/AIConfigurationController.php`**
   ```php
   #[Route('/admin/ai/mode1', name: 'admin_ai_mode1')]
   public function configureMode1(Request $request): Response { }
   
   #[Route('/api/admin/ai/test-connection', name: 'api_ai_test')]
   public function testConnection(Request $request): JsonResponse { }
   ```

### Mode 2 : Déploiement Automatisé

**Cas d'usage :** Aucune infrastructure IA existante. Obstack déploie automatiquement.

**Étapes du processus :**

```
1. Sélection hyperviseur (Proxmox/VMware/Nutanix)
   ↓
2. Paramètres de connexion + Test
   ↓
3. Sélection modèle (qwen3:4b ou qwen3:8b) — EXPLICITE
   ↓
4. Analyse ressources disponibles
   ↓
5. Déploiement automatisé
   └─ Création VM → OS → Docker → Ollama → Modèle → Services
   ↓
6. Enregistrement URL dans Obstack config
```

**Implémentation :**

1. **Service `HypervisorConnector.php` (interface)**
   ```php
   interface HypervisorConnector {
       public function testConnection(): bool;
       public function getAvailableResources(): Resources;
       public function createVM(VMSpec $spec): string; // retourne VM ID
       public function uploadISO(string $isoPath): string;
   }
   ```

2. **Implémentations :**
   ```php
   class ProxmoxConnector implements HypervisorConnector { }
   class VMwareConnector implements HypervisorConnector { }
   class NutanixConnector implements HypervisorConnector { }
   ```

3. **Service `AIDeploymentService.php`**
   ```php
   class AIDeploymentService {
       public function deploy(DeploymentRequest $request): DeploymentResult;
       // Orchestration :
       // 1. createVM()
       // 2. installOS()
       // 3. installDocker()
       // 4. installOllama()
       // 5. downloadModel()
       // 6. configureServices()
       // 7. registerInObstack()
   }
   ```

4. **Service `AIInstallationScript.php`**
   ```php
   class AIInstallationScript {
       // Génère les scripts d'installation :
       // - cloud-init pour déploiement VM
       // - Ansible playbooks pour config
       // - Docker Compose pour services
   }
   ```

5. **Form `AIDeploymentFormType.php`**
   ```php
   // Wizard multi-étapes :
   // Step 1: Choix hyperviseur
   // Step 2: Connexion + Test
   // Step 3: Sélection modèle
   // Step 4: Vérification ressources (affichage)
   // Step 5: Résumé + Confirmation déploiement
   ```

6. **Entity `AIInfrastructure.php`**
   ```php
   class AIInfrastructure {
       private int $id;
       private string $name;
       private string $mode; // 'existing' ou 'deployed'
       private string $provider; // 'ollama'
       private string $apiUrl;
       private string $model; // 'qwen3:4b' ou 'qwen3:8b'
       private string $status; // 'active', 'deploying', 'failed'
       private ?\DateTime $deployedAt;
       private array $hypervisorConfig; // Pour Mode 2
       private LocalCompany $company;
   }
   ```

---

## Architecture cible

### Structure des répertoires

```
src/
├── Service/
│   ├── AI/
│   │   ├── AiConfigurationService.php
│   │   ├── AiConfigurationValidator.php
│   │   ├── OllamaClient.php
│   │   ├── AIDeploymentService.php
│   │   ├── AIInstallationScript.php
│   │   ├── Hypervisor/
│   │   │   ├── HypervisorConnector.php (interface)
│   │   │   ├── ProxmoxConnector.php
│   │   │   ├── VMwareConnector.php
│   │   │   └── NutanixConnector.php
│   │   └── DTO/
│   │       ├── DeploymentRequest.php
│   │       ├── DeploymentResult.php
│   │       ├── ValidationResult.php
│   │       └── Resources.php
│   │
│   └── [Services existants]
│
├── Entity/
│   ├── AIInfrastructure.php
│   └── [Entities existantes]
│
├── Form/
│   └── AI/
│       ├── AIMode1ConfigurationType.php
│       ├── AIMode2DeploymentType.php
│       └── AIModelChoiceType.php
│
├── Controller/
│   ├── Admin/
│   │   └── AIConfigurationController.php
│   └── API/
│       └── AIIntegrationController.php
│
└── [Reste du code]
```

### Flux API

**Endpoint Mode 1 :**

```
POST /admin/ai/mode1/configure
├── Validation du formulaire
├── Test de connexion via OllamaClient
├── Enregistrement en base (AIInfrastructure)
└── Réponse JSON avec statut

GET /api/admin/ai/status
└── Retourne informations IA actuelles
```

**Endpoint Mode 2 :**

```
GET /admin/ai/mode2/wizard
└── Affiche formulaire wizard multi-étapes

POST /admin/ai/mode2/test-hypervisor
├── Test connexion hyperviseur
├── Retourne ressources disponibles
└── Valide configuration

POST /admin/ai/mode2/deploy
├── Validation configuration déploiement
├── Lancement déploiement asynchrone
├── Enqueue job Messenger
└── Retourne job ID pour suivi
```

---

## Contraintes techniques

### 1. Pas d'autodétection

Obstack **ne doit jamais** :

```php
// ❌ INTERDIT
scanNetworkForOllama();
discoverAIServices();
autoDetectModels();
lookForExistingAI();

// ✅ AUTORISÉ
getUserProvidedApiUrl();
testUserProvidedConnection();
```

### 2. Agents sans IA

```bash
# ❌ L'agent ne contient JAMAIS :
/opt/obsagent/ollama/
/opt/obsagent/models/
/opt/obsagent/python-ai/
/opt/obsagent/neo4j/

# ✅ L'agent contient SEULEMENT :
/opt/obsagent/bin/obsagent
/opt/obsagent/config/
/opt/obsagent/collectors/
```

### 3. Choix explicite du modèle

```php
// ❌ INTERDIT
$model = 'qwen3:' . (hasGpu ? '8b' : '4b'); // Auto-choix

// ✅ AUTORISÉ
// L'admin choisit explicitement dans le formulaire
// Validation : model ∈ {'qwen3:4b', 'qwen3:8b'}
```

### 4. Communication par API seulement

```php
// ❌ INTERDIT
$result = exec_remote_python_on_server();

// ✅ AUTORISÉ
$client = new OllamaClient($config['api_url']);
$result = $client->chat($prompt);
```

---

## Roadmap d'implémentation

### Phase 1 — Mode 1 (Semaines 1-2)

- [ ] Entity `AIInfrastructure.php`
- [ ] Migration Doctrine
- [ ] Service `OllamaClient.php`
- [ ] Service `AiConfigurationValidator.php`
- [ ] Form `AIMode1ConfigurationType.php`
- [ ] Controller `AIConfigurationController.php`
- [ ] Tests unitaires des services
- [ ] Documentation API

### Phase 2 — Mode 2 (Semaines 3-6)

- [ ] Interface `HypervisorConnector.php`
- [ ] Implémentation Proxmox
- [ ] Implémentation VMware
- [ ] Implémentation Nutanix
- [ ] Service `AIDeploymentService.php`
- [ ] Service `AIInstallationScript.php`
- [ ] Form `AIMode2DeploymentType.php` (wizard)
- [ ] Job/Command Messenger pour déploiement async
- [ ] Tests d'intégration
- [ ] Documentation déploiement

### Phase 3 — Évolutivité (Semaines 7+)

- [ ] Support de nouveaux fournisseurs IA (ex: vLLM)
- [ ] Support de nouveaux modèles
- [ ] Load balancing entre serveurs IA
- [ ] Haute disponibilité
- [ ] Métriques et monitoring de l'infrastructure IA
- [ ] Auto-scaling basé sur charge

---

## Guidelines de développement

### 1. Conception modulaire

```php
// ✅ BON : Interface + implémentations interchangeables
interface AiProvider {
    public function chat(string $prompt): string;
}

class OllamaProvider implements AiProvider { }
class VllmProvider implements AiProvider { }
class TuringProvider implements AiProvider { }

// ❌ MAUVAIS : Dépendance directe
class RcaEngine {
    public function __construct(private OllamaClient $ollama) { }
}
```

### 2. Séparation des responsabilités

```php
// ✅ GOOD : Chaque classe a une responsabilité
OllamaClient              // HTTP communication
AiConfigurationValidator  // Validation logique
AIDeploymentService       // Orchestration
HypervisorConnector       // Infrastructure API

// ❌ BAD : Un service fait tout
class AiManager {
    public function configAndDeployAndValidateAndTest() { }
}
```

### 3. Testabilité

```php
// ✅ GOOD : Dependency injection + interfaces
class AiConfigurationService {
    public function __construct(
        private AiProvider $aiProvider,
        private AIRepository $repository,
        private Logger $logger
    ) { }
}

// Testable :
$mockProvider = $this->createMock(AiProvider::class);
$service = new AiConfigurationService($mockProvider, ...);
```

### 4. Validation stricte

```php
// ✅ GOOD : Validation à chaque étape
if (!$validator->isValidApiUrl($url)) {
    throw new InvalidConfigurationException();
}

if (!$client->testConnection()) {
    throw new UnreachableAiException();
}

if (!in_array($model, ['qwen3:4b', 'qwen3:8b'])) {
    throw new UnsupportedModelException();
}

// ❌ BAD : Pas de validation
$client->chat($prompt); // Crashes silently
```

### 5. Logging et monitoring

```php
// ✅ GOOD : Logs structurés
$this->logger->info('AI Configuration Mode 1', [
    'provider' => $config['provider'],
    'model' => $config['model'],
    'test_result' => $testResult,
    'company_id' => $company->getId(),
]);

// ❌ BAD : Pas de logs
$client->chat($prompt);
```

### 6. Gestion d'erreurs

```php
// ✅ GOOD : Erreurs claires et traçables
try {
    $client->chat($prompt);
} catch (UnreachableAiException $e) {
    // L'admin sait que l'infrastructure IA est down
    $this->logger->critical('AI Infrastructure unreachable', ['url' => $url]);
    throw new AiServiceUnavailableException('Please check AI infrastructure');
} catch (ModelNotAvailableException $e) {
    // Modèle sélectionné non disponible
    throw new InvalidConfigurationException('Model qwen3:8b not found on server');
}
```

---

## Prochaines étapes

1. **Créer les entities et migrations** → Base de données
2. **Implémenter Mode 1** → Configuration manuelle
3. **Implémenter Mode 2** → Déploiement automatisé
4. **Intégrer dans UI admin** → Wizards et formulaires
5. **Documenter API** → Guide d'intégration
6. **Ajouter tests** → Couverture > 80%
7. **Déployer progressivement** → MVP → Phase 2 → Évolutivité

---

## Références

- [README AI Integration](../README.md#-architecture-et-intégration-ia-centralisée)
- [Ollama API Docs](https://github.com/ollama/ollama/blob/main/docs/api.md)
- [Proxmox API](https://pve.proxmox.com/pve-docs/api-viewer/)
- [SOLID Principles](https://en.wikipedia.org/wiki/SOLID)
