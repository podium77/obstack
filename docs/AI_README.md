# Documentation - Intégration IA Centralisée

## 📚 Guides d'Architecture

### Pour les administrateurs

- **[README - Architecture IA](../README.md#-architecture-et-intégration-ia-centralisée)** 
  - Vue d'ensemble des principes
  - Description des deux modes (existant vs déploiement)
  - Architecture cible
  - Contraintes principales

### Pour les développeurs

#### 1. **Guide d'Implémentation** - [AI_INTEGRATION.md](AI_INTEGRATION.md)
   - Principes fondamentaux détaillés
   - Mode 1 : Configuration IA existante
   - Mode 2 : Déploiement automatisé
   - Architecture cible et structure des répertoires
   - Flux API complets
   - Roadmap d'implémentation (3 phases)
   - Guidelines de développement (6 principes)
   
   **Sections principales :**
   - [Principes fondamentaux](AI_INTEGRATION.md#principes-fondamentaux)
   - [Mode 1 : IA Existante](AI_INTEGRATION.md#mode-1--ia-existante)
   - [Mode 2 : Déploiement Automatisé](AI_INTEGRATION.md#mode-2--déploiement-automatisé)
   - [Architecture cible](AI_INTEGRATION.md#architecture-cible)
   - [Roadmap d'implémentation](AI_INTEGRATION.md#roadmap-dimplémentation)

#### 2. **Contraintes Architecturales** - [AI_ARCHITECTURE_CONSTRAINTS.md](AI_ARCHITECTURE_CONSTRAINTS.md)
   - **Checklist de conformité** obligatoire (14 catégories)
   - **Anti-patterns** à JAMAIS utiliser
   - **Patterns** à respecter obligatoirement
   - Revue de code : questions pour les PR
   - Tests de conformité example
   
   **Sections principales :**
   - [Checklist de Conformité](AI_ARCHITECTURE_CONSTRAINTS.md#-checklist-de-conformité)
   - [Anti-patterns - JAMAIS](AI_ARCHITECTURE_CONSTRAINTS.md#-anti-patterns---jamais)
   - [Patterns - À respecter](AI_ARCHITECTURE_CONSTRAINTS.md#-patterns---à-respecter)
   - [Revue de Code](AI_ARCHITECTURE_CONSTRAINTS.md#-revue-de-code-code-review)

---

## 🎯 Principes Clés

### IA Centralisée
- L'IA est **toujours** sur l'infrastructure centralisée
- Agents et hôtes surveillés restent légers et sans IA

### Pas d'Autodétection
- **Aucun scan** automatique du réseau
- **Aucune détection** automatique de services IA
- Configuration manuelle uniquement par l'administrateur

### Choix Explicite
- Modèle sélectionné explicitement : `qwen3:4b` ou `qwen3:8b`
- Aucun choix automatique basé sur les ressources

### Architecture Modulaire
- Communication uniquement par API REST documentée
- Remplacement facile du fournisseur IA
- Évolution indépendante

---

## 📋 Ressources par Rôle

### 👨‍💼 Admin Système
**Lire d'abord :** [README - Architecture IA](../README.md#-architecture-et-intégration-ia-centralisée)

Questions répondues :
- Comment configurer une IA existante ?
- Comment déployer une nouvelle infrastructure IA ?
- Quels hyperviseurs sont supportés ?
- Quels modèles sont disponibles ?

### 👨‍💻 Développeur Backend
**Lire d'abord :** [Guide d'Implémentation](AI_INTEGRATION.md)

Questions répondues :
- Comment structurer le code ?
- Quels services créer ?
- Quelles entities en base de données ?
- Quels endpoints API implémenter ?
- Roadmap de développement ?

### 🔍 Code Reviewer
**Lire d'abord :** [Contraintes Architecturales](AI_ARCHITECTURE_CONSTRAINTS.md)

Questions répondues :
- Qu'est-ce qu'on peut FAIRE ?
- Qu'est-ce qu'on JAMAIS faire ?
- Quels tests implémenter ?
- Questions à poser lors d'une PR ?

### 🏗️ Architecte
**Lire tout :**
1. [README - Architecture IA](../README.md#-architecture-et-intégration-ia-centralisée) - Vue d'ensemble
2. [Guide d'Implémentation](AI_INTEGRATION.md) - Design détaillé
3. [Contraintes Architecturales](AI_ARCHITECTURE_CONSTRAINTS.md) - Conformité

---

## 🚀 Guide de Démarrage Rapide

### Pour implémenter Mode 1 (IA Existante)

1. **Créer l'entity**
   - Lire : [AI_INTEGRATION.md - Entity AIInfrastructure](AI_INTEGRATION.md#service-aiinstallationscriptphp)
   - Créer : `src/Entity/AIInfrastructure.php`

2. **Créer les services**
   - `src/Service/AI/OllamaClient.php` - Client HTTP Ollama
   - `src/Service/AI/AiConfigurationValidator.php` - Validation
   - `src/Service/AI/AiConfigurationService.php` - Orchestration

3. **Créer le formulaire**
   - `src/Form/AI/AIMode1ConfigurationType.php` - Formulaire Mode 1

4. **Créer le contrôleur**
   - `src/Controller/Admin/AIConfigurationController.php` - Routes Mode 1

5. **Écrire les tests**
   - Tests unitaires des services
   - Tests d'intégration des endpoints
   - Tests de conformité (voir AI_ARCHITECTURE_CONSTRAINTS.md)

### Pour implémenter Mode 2 (Déploiement Auto)

1. **Créer les hyperviseur connectors**
   - Interface : `src/Service/AI/Hypervisor/HypervisorConnector.php`
   - Impl. Proxmox : `src/Service/AI/Hypervisor/ProxmoxConnector.php`
   - Impl. VMware : `src/Service/AI/Hypervisor/VMwareConnector.php`
   - Impl. Nutanix : `src/Service/AI/Hypervisor/NutanixConnector.php`

2. **Créer les services de déploiement**
   - `src/Service/AI/AIDeploymentService.php` - Orchestration
   - `src/Service/AI/AIInstallationScript.php` - Génération scripts

3. **Créer le wizard formulaire**
   - `src/Form/AI/AIMode2DeploymentType.php` - Wizard multi-étapes

4. **Créer les jobs Messenger**
   - `src/Message/AIDeploymentMessage.php`
   - `src/MessageHandler/AIDeploymentHandler.php`

5. **Écrire les tests**
   - Tests des connectors
   - Tests d'orchestration
   - Tests de conformité

---

## 📊 Statut de l'Implémentation

### Phase 1 - Mode 1 (Pas encore commencée)
- [ ] Entity `AIInfrastructure`
- [ ] Service `OllamaClient`
- [ ] Service `AiConfigurationValidator`
- [ ] Form `AIMode1ConfigurationType`
- [ ] Controller `AIConfigurationController`
- [ ] Tests & Documentation

### Phase 2 - Mode 2 (Pas encore commencée)
- [ ] Hypervisor Connectors (Proxmox, VMware, Nutanix)
- [ ] Service `AIDeploymentService`
- [ ] Service `AIInstallationScript`
- [ ] Form `AIMode2DeploymentType`
- [ ] Job Messenger
- [ ] Tests & Documentation

### Phase 3 - Évolutivité (Pas encore commencée)
- [ ] Support nouveaux fournisseurs
- [ ] Support nouveaux modèles
- [ ] Load balancing
- [ ] Haute disponibilité
- [ ] Auto-scaling

---

## 🔗 Liens Rapides

| Document | Taille | Usage |
|----------|--------|-------|
| [README - Architecture IA](../README.md#-architecture-et-intégration-ia-centralisée) | ~3KB | Vue d'ensemble |
| [AI_INTEGRATION.md](AI_INTEGRATION.md) | ~14KB | Guide complet dev |
| [AI_ARCHITECTURE_CONSTRAINTS.md](AI_ARCHITECTURE_CONSTRAINTS.md) | ~10KB | Contraintes & conformité |

---

## ❓ Questions Fréquentes

**Q: Puis-je déployer Ollama sur les agents ?**  
A: NON. C'est explicitement interdit. Les agents restent légers (collecte uniquement).

**Q: Puis-je auto-détecter une infrastructure Ollama existante ?**  
A: NON. L'admin doit renseigner manuellement l'URL et les paramètres.

**Q: Puis-je choisir automatiquement `qwen3:8b` si la machine a 32GB RAM ?**  
A: NON. L'admin doit choisir explicitement dans le formulaire.

**Q: Puis-je supporter Ollama + vLLM + autre ?**  
A: OUI, mais via l'interface `AiProvider` avec implémentations séparées.

**Q: Quelle est la structure source ?**  
A: Voir [AI_INTEGRATION.md - Structure des répertoires](AI_INTEGRATION.md#structure-des-répertoires)

**Q: Quelle est la roadmap ?**  
A: Voir [AI_INTEGRATION.md - Roadmap d'implémentation](AI_INTEGRATION.md#roadmap-dimplémentation)

---

## ⚠️ Points Critiques

1. **JAMAIS de scan réseau** - Voir [Anti-patterns](AI_ARCHITECTURE_CONSTRAINTS.md#scan-automatique)
2. **JAMAIS d'IA sur agents** - Voir [Agents sans IA](AI_ARCHITECTURE_CONSTRAINTS.md#agents-sans-ia)
3. **JAMAIS de choix auto du modèle** - Voir [Auto-sélection](AI_ARCHITECTURE_CONSTRAINTS.md#auto-sélection-du-modèle)
4. **JAMAIS de communication hors-API** - Voir [Communication](AI_ARCHITECTURE_CONSTRAINTS.md#communication-en-dehors-de-lapi)

---

## 📞 Support

Avez-vous des questions sur l'intégration IA ?

- **Clarifications architecture :** Consultez [README](../README.md#-architecture-et-intégration-ia-centralisée)
- **Détails implémentation :** Consultez [AI_INTEGRATION.md](AI_INTEGRATION.md)
- **Vérification conformité :** Consultez [AI_ARCHITECTURE_CONSTRAINTS.md](AI_ARCHITECTURE_CONSTRAINTS.md)
- **Issues :** Utilisez les labels `ai`, `architecture`, `constraints` sur GitHub

---

**Dernière mise à jour :** 2026-07-03  
**Mainteneur :** Architecture Team / GitHub Copilot
