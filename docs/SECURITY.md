# 🔐 Guide de Sécurité - Obstack Admin API

## Vue d'ensemble

Ce guide couvre les meilleures pratiques de sécurité pour déployer et maintenir Obstack Admin API en production.

---

## 1. Sécurité de l'Application

### 1.1 Chiffrement

#### Encryption des Mots de Passe Base de Données
```
Algorithme: AES-256-CBC
IV: Aléatoire par encryption
Clé: SHA-256 dérivée de APP_ENCRYPTION_KEY
```

**Génération de clé (Production):**
```bash
# Générer clé sécurisée
APP_ENCRYPTION_KEY=$(openssl rand -hex 32)

# Sauvegarder dans .env
echo "APP_ENCRYPTION_KEY=$APP_ENCRYPTION_KEY" >> .env.production

# ⚠️ JAMAIS dans git, utiliser secrets management
# Exemple AWS Secrets Manager:
aws secretsmanager create-secret \
  --name obstack/encryption-key \
  --secret-string "$APP_ENCRYPTION_KEY"
```

#### Hachage des Mots de Passe Utilisateur
```
Algorithme: bcrypt
Cost: 12 (2025-compatible, ~0.5 secondes par hash)
Utilisateurs affectés: LocalUser, CompanyUser
```

**Configuration:**
```yaml
# config/packages/security.yaml
password_hashers:
  App\Entity\LocalUser:
    algorithm: bcrypt
    cost: 12  # Augmenter si CPU permet (max 15)
  App\Entity\CompanyUser:
    algorithm: bcrypt
    cost: 12
```

### 1.2 Authentification

#### JWT Tokens
- **Durée de vie:** 1 heure (configurable)
- **Refresh token:** 30 jours
- **Stockage:** HTTP-only cookies (non exposé au JS)
- **Transmission:** Header Authorization: Bearer

```bash
# Générer JWT secret
KERNEL_SECRET=$(openssl rand -hex 32)
```

#### Multi-tenant Isolation
```php
// Toutes les requêtes filtrées par tenant_id
SELECT * FROM users WHERE tenant_id = $currentTenant
```

### 1.3 Validation & Sanitization

#### Input Validation
```php
// Tous les inputs validés via Symfony Validator
#[Assert\Email]
private string $email;

#[Assert\Length(min: 8, max: 255)]
private string $password;

#[DatabaseStructureName]
private string $tableName;
```

#### Output Encoding
```php
// JSON output automatiquement encoded
// XSS: Mitigé par Content-Type: application/json header
// SQL Injection: Mitigé par Doctrine ORM avec prepared statements
```

---

## 2. Sécurité du Transport

### 2.1 HTTPS/TLS

**Obligatoire en Production:**
```bash
# Obtenir certificat Let's Encrypt
certbot certonly --webroot -w /path/to/public -d api.obstack.local

# Configuration Nginx
ssl_certificate /etc/letsencrypt/live/api.obstack.local/fullchain.pem;
ssl_certificate_key /etc/letsencrypt/live/api.obstack.local/privkey.pem;

# Rediriger HTTP → HTTPS
server {
    listen 80;
    return 301 https://$host$request_uri;
}
```

### 2.2 Headers de Sécurité

```
# Symfony ajoute automatiquement:
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Content-Security-Policy: default-src 'self'
```

### 2.3 CORS

**Configuration actuelle (ouvert):**
```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization
```

**Pour Production (restreindre):**
```php
// src/EventListener/CorsListener.php
$allowedOrigins = [
    'https://admin.obstack.local',
    'https://app.obstack.local',
];

if (in_array($origin, $allowedOrigins)) {
    $response->headers->set('Access-Control-Allow-Origin', $origin);
}
```

---

## 3. Sécurité de la Base de Données

### 3.1 Connexions Distantes

#### Credentials Sécurisés
```
✅ Stockés: Chiffrés en AES-256-CBC dans database
❌ Éviter: En plaintext dans config
❌ Éviter: Dans les logs
```

#### SSL/TLS pour Connexions DB
```php
// config/services.yaml
App\DatabaseConnector\PostgreSQLConnector:
  arguments:
    $sslMode: 'require'
    $sslCert: '/path/to/client-cert.pem'
    $sslKey: '/path/to/client-key.pem'
    $sslRootCert: '/path/to/ca-cert.pem'
```

### 3.2 Accès Database

#### Utilisateur DB limité
```sql
-- PostgreSQL: Créer utilisateur limité
CREATE USER obstack_app WITH PASSWORD 'secure_password';

-- Donner permissions minimales
GRANT CONNECT ON DATABASE obstack TO obstack_app;
GRANT USAGE ON SCHEMA public TO obstack_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO obstack_app;

-- Futures tables
ALTER DEFAULT PRIVILEGES IN SCHEMA public 
  GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO obstack_app;
```

#### IP Whitelisting
```bash
# postgresql.conf
listen_addresses = '192.168.1.10'  # Uniquement Obstack app

# pg_hba.conf
host    obstack    obstack_app    192.168.1.10/32    md5
```

### 3.3 Sauvegarde Sécurisée

```bash
# Backup avec encryption
pg_dump obstack | \
  openssl enc -aes-256-cbc -pass file:/path/to/backup.key | \
  gzip > obstack_backup_$(date +%Y%m%d).sql.gz.enc

# Restore
zcat obstack_backup_20251221.sql.gz.enc | \
  openssl enc -aes-256-cbc -d -pass file:/path/to/backup.key | \
  psql obstack
```

---

## 4. Sécurité de l'Infrastructure

### 4.1 Firewall

```bash
# UFW (Uncomplicated Firewall)
sudo ufw default deny incoming
sudo ufw default allow outgoing

# SSH (limiter par IP)
sudo ufw allow from 203.0.113.0/24 to any port 22

# HTTP/HTTPS (limiter par rate)
sudo ufw allow from any to any port 80
sudo ufw allow from any to any port 443

# PostgreSQL (uniquement app server)
sudo ufw allow from 192.168.1.10 to any port 5432

# Redis (si utilisé, uniquement localhost)
sudo ufw allow from 127.0.0.1 to any port 6379
```

### 4.2 Fail2Ban (Protection Brute Force)

```ini
# /etc/fail2ban/jail.local
[sshd]
enabled = true
maxretry = 3
findtime = 600
bantime = 3600

[obstack-auth]
enabled = true
port = http,https
filter = obstack-auth
logpath = /var/log/obstack/auth.log
maxretry = 5
findtime = 300
bantime = 900
```

### 4.3 Rate Limiting au Niveau Infrastructure

```nginx
# nginx.conf
limit_req_zone $binary_remote_addr zone=api_limit:10m rate=100r/m;
limit_req_zone $binary_remote_addr zone=query_limit:10m rate=10r/m;

server {
    # Endpoints de requête (strict)
    location /api/admin/database/.*/query {
        limit_req zone=query_limit burst=2 nodelay;
        proxy_pass http://php-fpm;
    }

    # Autres endpoints (relâché)
    location /api/admin/ {
        limit_req zone=api_limit burst=10 nodelay;
        proxy_pass http://php-fpm;
    }
}
```

---

## 5. Monitoring & Alerting

### 5.1 Logging Sécurité

```php
// Tous les événements sensibles loggés
- Login attempts (succès/échec)
- Permission changes
- Database connection operations
- Query executions
- Failed access attempts
- Rate limit violations
- Encryption operations
```

**Configuration:**
```yaml
# config/packages/monolog.yaml
monolog:
  handlers:
    security:
      type: rotating_file
      path: '%kernel.logs_dir%/security.log'
      level: info
      max_files: 90  # 90 jours
      formatter: json
```

### 5.2 Audit Trail

**Automatiquement enregistré:**
```json
{
  "id": 1,
  "action": "database_query",
  "user_id": 1,
  "resource_type": "database_connection",
  "resource_id": 5,
  "old_values": {},
  "new_values": {"host": "prod.db.com"},
  "ip_address": "203.0.113.42",
  "user_agent": "curl/7.64.1",
  "http_method": "PUT",
  "endpoint": "/api/admin/database-connections/5",
  "status": "success",
  "error_message": null,
  "created_at": "2025-12-21T10:00:00Z"
}
```

### 5.3 Alertes

```bash
# Installation Sentry
# https://sentry.io/

# Configuration
SENTRY_DSN=https://key@sentry.io/123456

# Package
composer require sentry/sentry-symfony
```

**Alertes importantes:**
- ❌ Authentication failures (> 5 in 5min)
- ❌ Rate limit violations (> 100 in 1h)
- ❌ Database connection errors
- ❌ Encryption key access
- ❌ Audit log write failures
- ❌ Unauthorized API access (403)

---

## 6. Gestion des Secrets

### 6.1 Stockage Sécurisé

**❌ Ne JAMAIS:**
- Commiter secrets en git
- Logger les secrets
- Exposer en error messages
- Utiliser defaults en production

**✅ Utiliser:**
```bash
# Développement: .env.local (gitignored)
APP_ENCRYPTION_KEY=dev-key-only-for-testing

# Production: Secrets Manager
- AWS Secrets Manager
- HashiCorp Vault
- Azure Key Vault
- Kubernetes Secrets

# Exemple: AWS Secrets Manager
aws secretsmanager get-secret-value \
  --secret-id obstack/encryption-key \
  --query SecretString \
  --output text
```

### 6.2 Rotation des Secrets

```bash
# Tous les 90 jours
0 0 1 * * /usr/local/bin/rotate-obstack-secrets.sh

# Script de rotation
#!/bin/bash
NEW_KEY=$(openssl rand -hex 32)
aws secretsmanager update-secret \
  --secret-id obstack/encryption-key \
  --secret-string "$NEW_KEY"

# Re-encrypt existing data (une fois)
php bin/console app:re-encrypt-passwords --old-key=$OLD_KEY --new-key=$NEW_KEY
```

---

## 7. Incident Response

### 7.1 Breach Detection

```bash
# Monitoring suspicious activity
- Bulk data exports (> 10000 rows)
- Failed login attempts (> 10 per user per hour)
- Unusual query patterns
- Access from new IPs
- Large file transfers
```

### 7.2 Mitigation Immédiate

```bash
# 1. Désactiver l'accès
php bin/console user:disable-user --email=compromised@user.local

# 2. Rotation des secrets
APP_ENCRYPTION_KEY=$(openssl rand -hex 32)

# 3. Audit trail analysis
SELECT * FROM audit_logs 
WHERE user_id = 123 
  AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY created_at DESC;

# 4. Database forensics
pg_dump --data-only obstack > forensics_dump.sql

# 5. Alertes
- Notifier équipe sécurité
- Contacter utilisateurs affectés
- Documenter incident
```

---

## 8. Compliance & Audit

### 8.1 Conformité Standards

**GDPR (EU):**
- ✅ Audit logs de toutes accès
- ✅ Encryption des données sensibles
- ✅ Droit à l'oubli (script de suppression)
- ✅ Data portability (export JSON)

**SOC2 (USA):**
- ✅ Access controls (RBAC)
- ✅ Monitoring & logging
- ✅ Incident response plan
- ✅ Regular security audits

**ISO 27001:**
- ✅ Information security policy
- ✅ Access control procedures
- ✅ Encryption standards
- ✅ Incident management

**HIPAA (Healthcare):**
- ✅ User authentication (MFA ready)
- ✅ Data integrity checks
- ✅ Audit controls
- ✅ Access logs

### 8.2 Audit Trail Queries

```sql
-- Activité utilisateur
SELECT * FROM audit_logs 
WHERE user_id = ? 
  AND created_at BETWEEN ? AND ?;

-- Modifications de données sensibles
SELECT * FROM audit_logs 
WHERE resource_type IN ('database_connection', 'local_user')
  AND action IN ('update', 'delete');

-- Tentatives d'accès refusé
SELECT * FROM audit_logs 
WHERE status = 'failure'
  AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)
ORDER BY created_at DESC;

-- Requêtes exécutées
SELECT * FROM audit_logs 
WHERE action = 'database_query'
  AND endpoint LIKE '%/query'
ORDER BY created_at DESC;
```

---

## 9. Checklist Déploiement Sécurisé

- [ ] APP_ENCRYPTION_KEY générée (32 bytes hex)
- [ ] KERNEL_SECRET généré et sécurisé
- [ ] DATABASE_URL utilise password robuste
- [ ] SSL/TLS certificat installé
- [ ] HTTP redirection vers HTTPS activée
- [ ] CORS restreint à domaines approuvés
- [ ] Firewall configuré (SSH limité)
- [ ] Fail2Ban activé pour brute force
- [ ] Rate limiting en place (nginx/PHP)
- [ ] Logs centralisés (Sentry, ELK)
- [ ] Backups chiffrés et testés
- [ ] Secrets stockés en Key Vault
- [ ] Admin user créé avec mot de passe fort
- [ ] Database credentials limitées par IP
- [ ] SSH keys passwordless configurées
- [ ] Monitoring alertes configurées
- [ ] Incident response plan documenté
- [ ] Audit logs retenus 90+ jours
- [ ] Tests de sécurité effectués
- [ ] Compliance audit passed

---

## 10. Ressources

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Symfony Security Best Practices](https://symfony.com/doc/current/security.html)
- [PostgreSQL Security](https://www.postgresql.org/docs/current/sql-syntax.html)
- [TLS Configuration](https://ssl-config.mozilla.org/)
- [CIS Benchmarks](https://www.cisecurity.org/cis-benchmarks/)

---

**🔐 Sécurité = Responsabilité Partagée**

Pour toute question de sécurité, contactez: security@obstack.local
