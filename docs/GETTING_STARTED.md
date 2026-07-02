# 🎬 Guide de Démarrage - Obstack Admin API

## Prérequis

- PHP 8.4+
- Symfony 7.4
- PostgreSQL 16 ou MySQL 8.0+
- Composer
- Redis (optionnel, pour rate limiting en prod)

---

## ⚡ Installation Rapide

### 1. Cloner le projet
```bash
git clone <repo-url> obstack
cd obstack
```

### 2. Installer les dépendances
```bash
composer install
```

### 3. Configurer l'environnement
```bash
# Copier .env.example → .env (si nécessaire)
cp .env .env.local

# Générer clé encryption
APP_ENCRYPTION_KEY=$(openssl rand -hex 32)
echo "APP_ENCRYPTION_KEY=$APP_ENCRYPTION_KEY" >> .env.local

# Configurer DATABASE_URL
# Exemple PostgreSQL:
# DATABASE_URL="postgresql://user:password@localhost:5432/obstack?serverVersion=16"
```

### 4. Créer la base de données
```bash
# Créer la BD
php bin/console doctrine:database:create

# Exécuter les migrations
php bin/console doctrine:migrations:migrate
```

### 5. Créer l'administrateur
```bash
php bin/console app:user:create-admin \
  --email=admin@obstack.local \
  --password=SecurePassword123 \
  --name='Administrator'
```

### 6. Démarrer le serveur
```bash
# Développement
symfony serve -d

# Production (avec Nginx/Apache)
# Voir documentation Symfony deployment
```

**API disponible:** `http://localhost:8000/api/admin/`

---

## 🔑 Authentification

### Obtenir un Token
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "admin",
    "password": "SecurePassword123"
  }'
```

Réponse:
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "user": {
    "id": 1,
    "email": "admin@obstack.local",
    "displayName": "Administrator",
    "isGlobalAdmin": true
  }
}
```

### Utiliser le Token
```bash
curl -X GET http://localhost:8000/api/admin/database-connections \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

### 🛡️ Authentification Admin Global

L'**Admin Global** est un utilisateur système avec accès complet, sans limitation d'entreprise.

#### Créer un Admin Global

```bash
php bin/console app:user:create-admin
```

Ou en non-interactif :

```bash
php bin/console app:user:create-admin \
  --email=admin@obstack.local \
  --password=SecurePassword123 \
  --name="Administrator"
```

#### Se connecter en tant qu'Admin Global

**Via Interface Web** (formulaire de login Symfony) :
1. Remplissez **Identifiant** : `admin` (ou votre email)
2. Remplissez **Mot de passe** : Le mot de passe défini
3. ☑️ Cochez la case **"Se connecter comme Admin Global"** (avec icône 🛡️)
4. Cliquez **Se connecter**

**Remarque** : Cette case n'est visible que pour les admins globaux. Les utilisateurs normaux ne peuvent pas y accéder.

#### Caractéristiques Admin Global

| Propriété | Valeur |
|-----------|--------|
| **Username** | `admin` (fixe) |
| **Rôles** | `ROLE_GLOBAL_ADMIN`, `ROLE_ADMIN`, `ROLE_SUPERADMIN` |
| **Entreprise** | Aucune (pas de limitation) |
| **Accès** | Système complet + Dashboard Global |
| **Permissions** | Tous les endpoints `#[IsGranted('ROLE_ADMIN')]` |

#### Dashboard Admin Global

Après connexion en tant qu'Admin Global, vous êtes automatiquement redirigé vers le **Dashboard Admin Global** à l'URL : `/dashboard`

Ce dashboard offre :
- ✅ **Accès système complet** sans limitation d'entreprise
- ✅ **Admin Console** pour gérer les configurations système
- ✅ **Vue globale** des entreprises en base
- ✅ **Création d'instances** optionnelle (pour test)
- ✅ **Permissions de superadmin** sur toutes les ressources

**Remarque Important** : Les admins globaux peuvent créer une nouvelle instance obstack en cliquant sur "Créer une Instance" depuis le dashboard, mais n'y sont pas obligés. Contrairement aux utilisateurs normaux qui doivent créer une entreprise pour accéder à l'application, les admins globaux peuvent ignorer complètement cette étape.

### Outre-passer la Création d'Instance (Admin Global)

Si vous êtes connecté en tant qu'Admin Global et accédez à la page `/register` (création d'instance) :
- ✅ Vous êtes **automatiquement redirigé** vers le Dashboard
- ❌ La création d'instance est **désactivée** pour les admins globaux
- ✅ Vous accédez **directement** au système sans configuration d'entreprise

Cette protection empêche les admins globaux de créer involontairement une instance en double.

#### Authentification API (Admin Global)

Les admins globaux peuvent aussi s'authentifier via l'API JSON :

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "username": "admin",
    "password": "SecurePassword123"
  }'
```

Réponse:
```json
{
  "success": true,
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refreshToken": "...",
    "expiresAt": "2026-07-02T13:00:00+00:00",
    "expiresIn": 3600
  },
  "user": {
    "id": 1,
    "email": "admin@obstack.local",
    "displayName": "Administrator",
    "isGlobalAdmin": true
  }
}
```

---

## 📍 Endpoints Principaux

### 1. Gestion des Connexions DB

#### Créer une connexion
```bash
curl -X POST http://localhost:8000/api/admin/database-connections \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Production PostgreSQL",
    "type": "postgresql",
    "host": "db.example.com",
    "port": 5432,
    "database": "production",
    "username": "app_user",
    "password": "SecurePassword123",
    "advancedOptions": {
      "sslMode": "require",
      "connectionTimeout": 10
    }
  }'
```

#### Lister les connexions
```bash
curl -X GET http://localhost:8000/api/admin/database-connections \
  -H "Authorization: Bearer $TOKEN"
```

Réponse:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Production PostgreSQL",
      "type": "postgresql",
      "host": "db.example.com",
      "port": 5432,
      "database": "production",
      "username": "app_user",
      "active": true,
      "tested": true,
      "lastTestedAt": "2025-12-21T10:00:00Z"
    }
  ],
  "message": "Database connections retrieved successfully"
}
```

#### Tester une connexion
```bash
curl -X POST http://localhost:8000/api/admin/database-connections/1/test \
  -H "Authorization: Bearer $TOKEN"
```

### 2. Navigation de Base de Données

#### Lister les structures (schémas/tables)
```bash
curl -X GET "http://localhost:8000/api/admin/database/1/structures" \
  -H "Authorization: Bearer $TOKEN"
```

Réponse:
```json
{
  "success": true,
  "data": [
    {
      "schema": "public",
      "name": "users",
      "type": "table",
      "columns": ["id", "email", "created_at"]
    },
    {
      "schema": "public",
      "name": "orders",
      "type": "table",
      "columns": ["id", "user_id", "total"]
    }
  ]
}
```

#### Récupérer les données d'une table
```bash
curl -X GET "http://localhost:8000/api/admin/database/1/data?structure=public.users&limit=50&offset=0" \
  -H "Authorization: Bearer $TOKEN"
```

Réponse:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "email": "admin@obstack.local",
      "created_at": "2025-12-21T10:00:00Z"
    }
  ],
  "metadata": {
    "limit": 50,
    "offset": 0,
    "count": 1,
    "total": 1
  }
}
```

#### Exécuter une requête personnalisée
```bash
curl -X POST http://localhost:8000/api/admin/database/1/query \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "query": "SELECT * FROM users WHERE email LIKE ?",
    "params": ["%admin%"]
  }'
```

### 3. Audit Logs

#### Lister les logs
```bash
curl -X GET "http://localhost:8000/api/admin/audit/logs?limit=50&action=database_query&status=success" \
  -H "Authorization: Bearer $TOKEN"
```

#### Voir l'activité d'un utilisateur
```bash
curl -X GET "http://localhost:8000/api/admin/audit/user/1" \
  -H "Authorization: Bearer $TOKEN"
```

#### Voir les tentatives d'accès refusé
```bash
curl -X GET "http://localhost:8000/api/admin/audit/access-denied?hours=24" \
  -H "Authorization: Bearer $TOKEN"
```

---

## 🛡️ Gestion des Sécurité

### Rate Limiting

**Limites par endpoint:**
- Requêtes personnalisées: **10 requests/minute**
- Opérations CRUD: **100 requests/minute**

Quand limité:
```
HTTP/1.1 429 Too Many Requests
{
  "success": false,
  "error": "Rate limit exceeded",
  "retry_after": 1234567890
}
```

### Gestion des Erreurs

**Tous les erreurs suivent ce format:**
```json
{
  "success": false,
  "error": "Description de l'erreur",
  "details": "Détails techniques (optionnel)"
}
```

**Codes HTTP:**
- `400` - Bad Request (validation, connection error)
- `401` - Unauthorized (missing/invalid token)
- `403` - Forbidden (permission denied)
- `429` - Too Many Requests (rate limited)
- `500` - Internal Server Error

---

## 🔧 Configuration

### Variables d'Environnement
```bash
# Sécurité
APP_ENCRYPTION_KEY=your-32-byte-hex-key

# Base de données
DATABASE_URL=postgresql://user:password@localhost/dbname

# Mailer (optionnel)
MAILER_DSN=smtp://localhost:1025

# App
APP_ENV=dev
APP_DEBUG=true
KERNEL_SECRET=your-secret-key
```

### Configuration Symfony

#### Services (config/services.yaml)
```yaml
services:
  # Automatically wired
  App\Service\RbacService: ~
  App\Service\AuditService: ~
  App\Service\AdminService: ~
  App\Service\RateLimitService: ~
```

#### Sécurité (config/packages/security.yaml)
```yaml
security:
  password_hashers:
    App\Entity\LocalUser:
      algorithm: bcrypt
      cost: 12
```

---

## 🧪 Testing

### Exécuter les tests
```bash
# Tous les tests
./bin/phpunit

# Un fichier spécifique
./bin/phpunit tests/Service/PasswordEncryptionServiceTest.php

# Un test spécifique
./bin/phpunit --filter testEncryptionAndDecryption
```

### Résultats attendus
```
Tests: 45, Assertions: 82
Passing: 41
Failures: 4 (pre-existing, non liés à Phase 4.5)
```

---

## 📊 Monitoring

### Logs
```bash
# Logs d'application
tail -f var/log/dev.log

# Logs de requête
tail -f var/log/request.log
```

### Métriques

#### Audit logs
```bash
php bin/console doctrine:query:dql \
  "SELECT a.action, COUNT(a) as count FROM App\Entity\AuditLog a GROUP BY a.action"
```

#### Utilisateurs actifs
```bash
php bin/console doctrine:query:dql \
  "SELECT COUNT(u) FROM App\Entity\LocalUser u WHERE u.isActive = true"
```

---

## 🚀 Déploiement Production

### 1. Préparer l'environnement
```bash
# Générer clés sécurisées
APP_ENCRYPTION_KEY=$(openssl rand -hex 32)
KERNEL_SECRET=$(openssl rand -hex 32)

# Sauvegarder dans .env.production
echo "APP_ENCRYPTION_KEY=$APP_ENCRYPTION_KEY" > .env.production
echo "KERNEL_SECRET=$KERNEL_SECRET" >> .env.production
```

### 2. Installer avec optimisation
```bash
composer install --no-dev --optimize-autoloader

# Précharger les classes
composer dump-autoload --optimize --classmap-authoritative
```

### 3. Optimiser Symfony
```bash
# Compiler les services
php bin/console cache:warmup --env=prod

# Générer assets
php bin/console asset-map:compile --env=prod
```

### 4. Configurer le serveur Web

#### Nginx
```nginx
server {
    listen 80;
    server_name api.obstack.local;
    root /path/to/obstack/public;

    location / {
        try_files $uri /index.php$is_args$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass php-fpm;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
}
```

#### Apache (avec mod_rewrite)
```apache
<Directory /path/to/obstack/public>
    AllowOverride All
    Allow from all
</Directory>
```

### 5. SSL/TLS
```bash
# Obtenir certificat Let's Encrypt
certbot certonly --webroot -w /path/to/obstack/public -d api.obstack.local
```

### 6. Supervisor (pour workers)
```ini
[program:obstack-messenger]
command=php /path/to/obstack/bin/console messenger:consume async --time-limit=3600
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/obstack/messenger.log
```

---

## 📞 Support & Troubleshooting

### Problème: "Cache not cleared"
```bash
php bin/console cache:clear --no-warmup
php bin/console cache:warmup
```

### Problème: "Database connection timeout"
```bash
# Vérifier connection string
php bin/console doctrine:query:dql "SELECT 1"

# Vérifier credentials
php bin/console doctrine:database:create --if-not-exists
```

### Problème: "Rate limit exceeded too quickly"
```bash
# Augmenter les limites dans RateLimitListener
# Ou implémenter Redis pour partage entre instances
```

### Problème: "Encryption key missing"
```bash
# Générer et configurer
APP_ENCRYPTION_KEY=$(openssl rand -hex 32)
```

---

## 📖 Ressources Additionnelles

- **Symfony Documentation:** https://symfony.com/doc
- **Doctrine ORM:** https://www.doctrine-project.org/
- **PostgreSQL:** https://www.postgresql.org/docs
- **Project Architecture:** `docs/ARCHITECTURE.md`
- **API Reference:** `docs/API_REFERENCE.md`

---

## ✅ Checklist Post-Installation

- [ ] .env configuré avec credentials
- [ ] APP_ENCRYPTION_KEY générée
- [ ] Database créée et migrée
- [ ] Admin user créé
- [ ] Serveur démarre sans erreur
- [ ] Cache cleared
- [ ] Tests passent
- [ ] API répond sur /api/admin/database-connections
- [ ] Token JWT générées correctement
- [ ] Audit logs enregistrés

---

**🎉 Prêt à utiliser!**

Pour plus d'informations, consultez:
- `contexte/13_PHASE_4_5_SECURITY_INFRASTRUCTURE.md`
- `docs/API_REFERENCE.md`
- `contexte/00_PROJECT_SUMMARY.md`
