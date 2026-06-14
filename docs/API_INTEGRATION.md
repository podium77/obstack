# Guide d'intégration API

## Introduction

Ce guide décrit comment intégrer des systèmes externes avec l'API REST d'obstack. L'API est principalement utilisée par les agents pour s'enregistrer, envoyer des métriques et assurer un heartbeat. Elle peut également servir pour des intégrations internes et des récupérations de données.

## Authentification

L'API agent utilise une authentification par jeton Bearer.

### Format de l'en-tête

```http
Authorization: Bearer {TOKEN}
```

Le jeton est généré par la plateforme obstack et associé à un environnement / une entreprise.

## Endpoints principaux

### `GET /api/v1/agent/install/{token}`

- Description : retourne le script d'installation de l'agent.
- Usage : téléchargement ou pipe vers bash.
- Réponse : `application/x-sh`

### `POST /api/v1/agent/register`

- Description : enregistre un agent auprès de la plateforme.
- Utilisation : l'agent appelle cette route après installation pour initialiser son application et son environnement.
- Payload JSON :

```json
{
  "hostname": "server.example.com",
  "hostname_short": "server",
  "machine_type": "physical",
  "hypervisor": "KVM/QEMU",
  "vm_uuid": "01234567-89ab-cdef-0123-456789abcdef",
  "os_id": "ubuntu",
  "os_version": "22.04",
  "os_pretty": "Ubuntu 22.04 LTS",
  "kernel": "5.15.0-88-generic",
  "architecture": "x86_64",
  "cpu_model": "Intel(R) Xeon(R)",
  "cpu_cores": 4,
  "cpu_threads": 8,
  "ram_gb": 16.0,
  "serial": "ABCD1234",
  "manufacturer": "Dell Inc.",
  "product": "PowerEdge",
  "bios": "1.2.3",
  "timezone": "Europe/Paris",
  "is_k8s_node": false,
  "k8s_role": "worker",
  "technologies": [],
  "network_interfaces": [],
  "disks": []
}
```

- Réponse attendue : `200` ou `201`.

### `POST /api/v1/agent/metrics`

- Description : transmet des métriques collectées par un agent.
- Payload JSON :

```json
{
  "timestamp": 1717353600,
  "cpu_percent": 42.5,
  "cpu_per_core": [10.3, 12.1, 8.8, 9.5],
  "memory": {
    "total_mb": 8192,
    "used_mb": 2048,
    "percent": 25.0
  },
  "swap": {
    "total_mb": 2048,
    "used_mb": 100,
    "percent": 4.9
  },
  "disks": [
    {
      "mountpoint": "/",
      "device": "/dev/sda1",
      "fstype": "ext4",
      "total_gb": 120,
      "used_gb": 45,
      "used_percent": 37.5
    }
  ],
  "network": {
    "bytes_sent": 1234567,
    "bytes_recv": 2345678,
    "latency_ms": {
      "internet": 18.7,
      "loopback": 0.3
    }
  },
  "load_average": [0.12, 0.08, 0.05],
  "uptime_seconds": 86400,
  "process_count": 145,
  "agent_version": "2.1.0",
  "enabled_modules": ["prometheus", "loki"]
}
```

- Réponse : `success: true` et `snapshot_id`.

### `POST /api/v1/agent/heartbeat`

- Description : envoie un signal de vie périodique depuis l'agent.
- Payload JSON :

```json
{ "hostname": "server.example.com" }
```

- Réponse : `status: alive`, `timestamp`.

## Bonnes pratiques

### Validation du token

- Toujours vérifier que le token Bearer est valide avant d'envoyer des données.
- Les requêtes sans authentification doivent être rejetées avec `401 Unauthorized`.

### Sécurisation

- Utiliser HTTPS pour toutes les communications.
- Ne pas exposer le token dans les logs non chiffrés.
- Renouveler les tokens régulièrement si le workflow le nécessite.

### Gestion des erreurs

- Reprendre les envois sur erreur réseau temporaire (5xx ou timeouts).
- Ne pas rejouer aveuglément les données sur une erreur 4xx.
- Sur une erreur de validation de token, afficher un message clair dans les logs de l'agent.

## Exemple d’intégration avec curl

```bash
curl -X POST https://obstack.example.com/api/v1/agent/metrics \
  -H "Authorization: Bearer ${TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{
    "timestamp": 1717353600,
    "cpu_percent": 42.5,
    "memory": {"total_mb": 8192, "used_mb": 2048, "percent": 25.0},
    "disks": [{"mountpoint": "/", "device": "/dev/sda1", "total_gb": 120, "used_gb": 45, "used_percent": 37.5}]
  }'
```

## Intégration avancée

### Format de date

- Utiliser des timestamps Unix en secondes.
- Le champ `timestamp` doit être en UTC.

### Jeux de données facultatifs

- `technologies` : liste JSON de technologies détectées.
- `enabled_modules` : modules activés dans l’agent.
- `k8s_role` : `worker` ou `master` lorsque `is_k8s_node` est vrai.

## Débogage

### Vérifier la réponse de l’API

- `200` ou `201` : succès
- `401` : token invalide ou absent
- `403` : token expiré ou non autorisé
- `400` : erreur de payload
- `500` : problème serveur

### Logs côté agent

- Vérifier l’agent pour les erreurs d’envoi HTTP.
- Conserver les réponses du serveur pour analyse.

---

## Annexes

### Exemple de payload complet

```json
{
  "timestamp": 1717353600,
  "hostname": "server.example.com",
  "hostname_short": "server",
  "machine_type": "physical",
  "hypervisor": "KVM/QEMU",
  "vm_uuid": "01234567-89ab-cdef-0123-456789abcdef",
  "os_id": "ubuntu",
  "os_version": "22.04",
  "os_pretty": "Ubuntu 22.04 LTS",
  "kernel": "5.15.0-88-generic",
  "architecture": "x86_64",
  "cpu_model": "Intel(R) Xeon(R)",
  "cpu_cores": 4,
  "cpu_threads": 8,
  "ram_gb": 16.0,
  "serial": "ABCD1234",
  "manufacturer": "Dell Inc.",
  "product": "PowerEdge",
  "bios": "1.2.3",
  "timezone": "Europe/Paris",
  "is_k8s_node": false,
  "k8s_role": "worker",
  "technologies": ["nginx", "php-fpm", "docker"],
  "network_interfaces": [{"name": "eth0", "mac": "01:23:45:67:89:ab"}],
  "disks": [{"name": "sda1", "size": "120G", "type": "SSD", "transport": "nvme"}],
  "cpu_percent": 42.5,
  "cpu_per_core": [10.3, 12.1, 8.8, 9.5],
  "memory": {"total_mb": 8192, "used_mb": 2048, "percent": 25.0},
  "swap": {"total_mb": 2048, "used_mb": 100, "percent": 4.9},
  "network": {"bytes_sent": 1234567, "bytes_recv": 2345678, "latency_ms": {"internet": 18.7, "loopback": 0.3}},
  "load_average": [0.12, 0.08, 0.05],
  "uptime_seconds": 86400,
  "process_count": 145,
  "agent_version": "2.1.0",
  "enabled_modules": ["prometheus", "loki"]
}
```
