# Contraintes Architecturales - IA Centralisée

Ce document énumère les contraintes d'architecture à respecter **obligatoirement** lors de tout développement lié à l'intégration IA dans Obstack.

## 📋 Checklist de Conformité

À respecter pour toute PR touchant l'IA ou la configuration :

### Architecture

- [ ] L'IA n'est déployée **que** sur l'infrastructure centralisée
- [ ] Les agents d'observabilité restent légers (< 50MB, hors cache)
- [ ] Les agents **ne contiennent pas** :
  - Ollama ou autre LLM runtime
  - Modèles pré-téléchargés
  - Neo4j ou base de données dédiée à l'IA
  - Environnements Python spécialisés
- [ ] Communication observabilité ↔ IA **uniquement par API REST**
- [ ] Découplage total : remplacement d'Ollama ne modifie pas le core d'Obstack

### Configuration

- [ ] **Aucun scan automatique** du réseau ou des services
- [ ] **Aucune détection automatique** d'infrastructure IA existante
- [ ] Tous les paramètres IA sont fournis **manuellement par l'admin**
- [ ] Configuration stockée en base de données, pas en fichiers

### Modèles

- [ ] Seuls modèles supportés : `qwen3:4b` et `qwen3:8b`
- [ ] **Choix du modèle : explicite** (pas d'auto-sélection basée sur ressources)
- [ ] Aucun fallback automatique à un autre modèle
- [ ] Validation stricte : modèle ∈ liste des modèles autorisés

### Déploiement (Mode 2)

- [ ] Infrastructure IA : **Debian Trixie uniquement**
- [ ] Hyperviseurs supportés : Proxmox VE, VMware ESXi, Nutanix AHV
- [ ] **Aucun déploiement** en dehors de ces hyperviseurs
- [ ] Automatisation complète : VM → OS → Ollama → modèle → services

### API

- [ ] Endpoints API **documentés** et **versionnés**
- [ ] Interfaces publiques **extensibles** pour nouveaux fournisseurs
- [ ] Tests de connexion **obligatoires** avant d'enregistrer la config
- [ ] Timeouts configurables pour appels réseau

### Sécurité

- [ ] Clé API Ollama : champs **optionnel** mais hashée en base
- [ ] Pas de logs contenant des tokens ou clés API en clair
- [ ] Chiffrement des identifiants stockés (`symfony/security` ou similaire)
- [ ] Seul admin global peut configurer l'infrastructure IA

### Logging

- [ ] Tous les événements IA : création, test, déploiement, erreur
- [ ] Logs structurés avec contexte (company_id, user_id, timestamp)
- [ ] Erreurs claires et traçables pour diagnostiquer les problèmes

---

## ❌ Anti-patterns - JAMAIS

### Scan automatique

```php
// ❌ INTERDIT : Scan réseau
foreach (range(1, 255) as $ip) {
    $url = "192.168.1.$ip:11434";
    if (canConnect($url)) {
        registerOllama($url); // Auto-découverte
    }
}

// ❌ INTERDIT : Détection basée sur DNS
$ollama = dns_get_record('ollama.local');
if ($ollama) {
    registerOllama($ollama['ip']);
}
```

### Auto-sélection du modèle

```php
// ❌ INTERDIT : Choix auto basé sur ressources
if ($hypervisor->getAvailableMemory() > 32) {
    $model = 'qwen3:8b'; // Auto-choix = WRONG!
} else {
    $model = 'qwen3:4b';
}

// ✅ CORRECT : Admin choisit explicitement
// L'utilisateur sélectionne dans le formulaire:
// <select name="model">
//   <option value="qwen3:4b">Qwen3 4B</option>
//   <option value="qwen3:8b">Qwen3 8B</option>
// </select>
```

### Déploiement d'IA sur agents

```bash
# ❌ INTERDIT : Ollama dans l'image Docker agent
FROM debian:trixie
RUN apt-get install ollama  # NON!
RUN curl https://ollama.ai | bash # NON!

# ✅ CORRECT : Agent reste léger
FROM debian:trixie-slim
COPY obsagent /opt/obsagent
# Point final
```

### Communication en-dehors de l'API

```php
// ❌ INTERDIT : Appel direct au service Ollama depuis agent
$agent->executeRemote('curl http://ollama:11434/api/chat');

// ❌ INTERDIT : SSH vers la VM IA
$ssh->exec('ollama pull qwen3:8b');

// ✅ CORRECT : Uniquement par API
$client = new OllamaClient($config['api_url']);
$result = $client->chat('prompt');
```

### Dépendances fortes

```php
// ❌ INTERDIT : Couplage fort à Ollama
class RcaEngine {
    public function __construct(private OllamaClient $ollama) {
        // Déployer RCA = déployer Ollama ?
    }
}

// ✅ CORRECT : Interface indépendante
class RcaEngine {
    public function __construct(private AiProvider $provider) {
        // Peut utiliser Ollama, vLLM, API externe, etc.
    }
}

interface AiProvider {
    public function chat(string $prompt): string;
}
```

---

## ✅ Patterns - À respecter

### Validation stricte

```php
// ✅ BON : Validation à chaque étape
class AIConfigurationService {
    public function register(array $config): AIInfrastructure {
        // Étape 1 : Valider format
        if (!filter_var($config['api_url'], FILTER_VALIDATE_URL)) {
            throw new InvalidConfigurationException('Invalid API URL format');
        }
        
        // Étape 2 : Tester connexion
        if (!$this->client->testConnection($config['api_url'])) {
            throw new UnreachableAiException('Cannot reach API: ' . $config['api_url']);
        }
        
        // Étape 3 : Valider modèle
        $supportedModels = ['qwen3:4b', 'qwen3:8b'];
        if (!in_array($config['model'], $supportedModels)) {
            throw new UnsupportedModelException('Model must be ' . implode(' or ', $supportedModels));
        }
        
        // Étape 4 : Vérifier modèle disponible
        $availableModels = $this->client->listModels($config['api_url']);
        if (!in_array($config['model'], $availableModels)) {
            throw new ModelNotAvailableException('Model not found on server');
        }
        
        // Étape 5 : Persister
        return $this->repository->save(new AIInfrastructure($config));
    }
}
```

### Interfaces extensibles

```php
// ✅ BON : Interface qui permet l'extension
namespace App\Service\AI;

interface AiProvider {
    public function chat(string $prompt, array $options = []): ChatResponse;
    public function generate(string $prompt, array $options = []): GenerateResponse;
    public function getAvailableModels(): array;
    public function validateConnection(): bool;
}

// Implémentations :
class OllamaProvider implements AiProvider { }
class VllmProvider implements AiProvider { }
class ExternalApiProvider implements AiProvider { }

// Factory pattern :
class AiProviderFactory {
    public static function create(string $provider, array $config): AiProvider {
        return match($provider) {
            'ollama' => new OllamaProvider($config),
            'vllm' => new VllmProvider($config),
            'external' => new ExternalApiProvider($config),
            default => throw new UnsupportedProviderException(),
        };
    }
}
```

### Logging structuré

```php
// ✅ BON : Logs structurés avec contexte
$this->logger->info('AI Infrastructure registered', [
    'company_id' => $company->getId(),
    'user_id' => $user->getId(),
    'mode' => $config['mode'],      // 'existing' ou 'deployed'
    'provider' => $config['provider'], // 'ollama'
    'model' => $config['model'],       // 'qwen3:8b'
    'api_url' => $config['api_url'],
    'connection_test' => 'success',
    'timestamp' => date('c'),
]);

$this->logger->warning('AI Configuration attempt with unsupported model', [
    'requested_model' => 'gpt-4',
    'supported_models' => ['qwen3:4b', 'qwen3:8b'],
]);

$this->logger->error('AI Service connection failed', [
    'api_url' => $apiUrl,
    'error' => $exception->getMessage(),
    'attempt' => 1,
    'retry_scheduled' => true,
]);
```

### Tests de conformité

```php
// ✅ BON : Tests pour vérifier les contraintes
class AIArchitectureConformanceTest extends TestCase {
    public function testOllamaClientNeverRunsOnAgents(): void {
        // Vérifier que obsagent n'inclut pas ollama
        $this->assertFalse(
            file_exists('/opt/obsagent/ollama/bin/ollama')
        );
    }
    
    public function testModelChoiceIsExplicitNotAutomatic(): void {
        $config = ['model' => 'auto']; // ❌ Pas autorisé
        $this->expectException(InvalidConfigurationException::class);
        AIConfigurationValidator::validate($config);
    }
    
    public function testNoNetworkScan(): void {
        // S'assurer que la config demande les paramètres manuellement
        $this->assertFalse(method_exists(AIConfiguration::class, 'scanNetwork'));
        $this->assertFalse(method_exists(AIConfiguration::class, 'autoDetect'));
    }
    
    public function testCommunicationOnlyViaApi(): void {
        // Vérifier qu'on utilise uniquement l'API REST
        $reflection = new ReflectionClass(RcaEngine::class);
        $this->assertTrue($reflection->hasProperty('aiProvider'));
        $this->assertNotTrue($reflection->hasProperty('remoteShell'));
    }
}
```

---

## 🔍 Revue de Code (Code Review)

### Questions à poser pour toute PR :

1. **Agents impactés ?**
   - L'agent reste-t-il < 50MB (hors cache) ?
   - Aucun nouveau binaire Ollama/LLM/IA ajouté ?

2. **Configuration IA ?**
   - L'admin entre-t-il manuellement tous les paramètres ?
   - Aucun scan automatique ?
   - Aucune détection auto-service ?

3. **Modèles ?**
   - Seuls `qwen3:4b` et `qwen3:8b` sont supportés ?
   - Validation stricte du choix ?

4. **API ?**
   - Endpoints documentés ?
   - Tests de connexion obligatoires ?

5. **Découplage ?**
   - Code dépend-il d'une interface ou d'une implémentation concrète ?
   - Peut-on remplacer Ollama par autre chose sans refactoring ?

6. **Tests ?**
   - Coverage ≥ 80% ?
   - Tests des erreurs et cas limites ?

7. **Logging ?**
   - Logs structurés avec contexte ?
   - Pas de secrets en clair (tokens, clés API) ?

---

## 📚 Ressources

- [AI Integration Guide](./AI_INTEGRATION.md)
- [README - AI Architecture](../README.md#-architecture-et-intégration-ia-centralisée)
- [Symfony Service Container](https://symfony.com/doc/current/service_container.html)
- [SOLID Principles](https://en.wikipedia.org/wiki/SOLID)
- [DDD - Domain-Driven Design](https://martinfowler.com/bliki/DomainDrivenDesign.html)

---

**Dernière mise à jour :** 2026-07-03  
**Responsable :** Architecture Team
