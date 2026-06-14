# AGENTS — Spécification pour le dépôt `obstack`

Ce document décrit précisément comment les agents fonctionnent pour ce projet (endpoints, format des tokens, comportements attendus, scripts d'installation). Il sert de référence pour les développeurs et pour la génération automatique de scripts d'installation.

## Endpoints disponibles (API agent)
- Télécharger le script d'installation: `GET /api/v1/agent/install/{token}`
- Enregistrement initial de l'agent: `POST /api/v1/agent/register` (Bearer token)
- Envoyer métriques: `POST /api/v1/agent/metrics` (Bearer token)
- Heartbeat: `POST /api/v1/agent/heartbeat` (Bearer token)

Les routes sont implémentées dans `src/Controller/AgentApiController.php`.

## AgentToken (concept dans le code)
- Entité: `src/Entity/AgentToken.php`.
- Champs importants: `token` (valeur binaire hex), `installScript` (script shell retourné par `/install/{token}`), `environment`, `application` (optionnel), `modules` (JSON), `lastHeartbeatAt`.
- Validation du token: méthode `isValid()` (actif + non expiré).

## Types d'agents et règles métier

- Agent de base
  - Déployé/présent pour une `Company` ou via un `AgentToken` généré depuis l'UI.
  - Fonction: découverte (hardware, technologies), enregistrement (`/register`), envoi de métriques et heartbeat.
  - Ne fait PAS de remédiation automatique côté agent.

- Agent spécifique
  - Utilisé quand on crée explicitement un `Environment` + `Application` et que les services monitorés partagent le même hôte (même IP/hostname).
  - Contient modules additionnels (collecteurs, checks locaux) liés à ces applications.
  - Peut activer des remédiations seulement si le serveur l'autorise pour cette `Application`.

Remarque: côté serveur, l'association `AgentToken ↔ Application` est faite automatiquement lors de l'enregistrement si possible (`AgentApiController::createApplicationFromRegistration`).

## Comportement attendu de l'agent
- Enregistrement: l'agent POST `/register` avec payload contenant `hostname`, `hostname_short`, `machine_type`, `os_id`, `technologies`, `cpu_model`, `ram_gb`, etc. Le server renvoie l'`application_id` et les intervalles (`collect_interval`, `heartbeat_interval`).
- Cadences recommandées: `metrics` toutes les 60s, `heartbeat` toutes les 30s (valeurs renvoyées par `/register`).
- Buffering local: l'agent doit stocker localement métriques/événements si réseau indisponible et les renvoyer FIFO.
- Tests de connectivité: lors de l'activation d'un module, proposer un check (ping, port TCP, HTTP health) — `MetricCollector::measureLatencies` montre des exemples d'exécutions SSH.

## Intégration avec le code existant
- Collecte distante (serveur) : `src/Agent/MetricCollector.php` montre comment le serveur collecte via SSH (commande `top`, `free`, `df`, `systemctl`, `ping`, `dig`, etc.). Ce collector utilise `SshClient`/`SshConnection` et suppose:
  - Authentification SSH par clé (chemins dans l'entité `Application`: `sshKeyPath`, `sshUser`, `sshPort`).
  - Extension PHP `ssh2` installée sur le serveur (voir exception dans `SshClient::createConnection`).

## Exemples d'installateurs / packaging

- Binaire Rust (`obsagent`)
  - Build: `cargo build --release`
  - Packager l'exécutable en `.tar.gz` pour téléchargement.
  - Le serveur peut fournir l'artefact depuis l'UI ou via un URL signé.

- Script d'installation Python (modèle)
  - Le serveur génère `installScript` stocké dans `AgentToken.installScript` et servi via `/install/{token}`.
  - Le script doit: installer dépendances système (python3, venv), créer un `venv`, installer dépendances Python, déposer le script `obsagent.py`, et créer un service systemd pour démarrer au boot.
  - Exemple minimal de structure et de création de service se base sur le template d'installateur (fourni sur demande).

## UI / règles d'interaction (à afficher à l'utilisateur)
- Afficher un résumé clair des choix avant validation (type d'agent, modules, nom `obsagent`, remédiation activée ou non).
- Lors de création d'un agent spécifique: demander explicitement si tous les services monitorés partagent le même hôte; sinon refuser et proposer un déploiement par hôte.
- Proposer de télécharger `obsagent` (binaire ou script) directement depuis la fenêtre de création. Le nom de fichier recommandé: `obsagent-<company>-<env>.tar.gz` ou `install-obs-<company>-<env>.sh`.
- Dashboard agents: afficher état par token/application: `Enregistré`, `En attente de configuration`, `En erreur`, `Déconnecté` (basé sur `lastHeartbeatAt`).

## Remédiation et gouvernance
- Les remédiations automatiques sont déclenchées uniquement pour des `Application`s qui existent dans un `Environment` et lorsque le serveur l'autorise.
- L'agent de base ne peut pas effectuer de remédiation locale non autorisée.


---

Fichier(s) à consulter pour approfondir: `src/Controller/AgentApiController.php`, `src/Entity/AgentToken.php`, `src/Agent/MetricCollector.php`, `src/Agent/SshClient.php`.
