# Guide de configuration LDAP / OAuth2

Ce document présente la configuration de l'authentification LDAP et OAuth2 pour obstack.

## Vue d'ensemble

obstack peut s'intégrer à des annuaires LDAP/Active Directory et à des fournisseurs OAuth2/OIDC pour la gestion des utilisateurs et l'authentification.

- **LDAP** : utilisation traditionnelle des annuaires pour les utilisateurs et groupes
- **OAuth2 / OIDC** : intégration avec un fournisseur d'identité moderne

---

## 1. Configuration LDAP

### 1.1 Variables d'environnement

```env
LDAP_HOST=ldap.company.local
LDAP_PORT=389
LDAP_BASE_DN=dc=company,dc=local
LDAP_BIND_DN=cn=ldap-reader,dc=company,dc=local
LDAP_BIND_PASSWORD=changeme
LDAP_USER_BASE_DN=ou=users,dc=company,dc=local
LDAP_GROUP_BASE_DN=ou=groups,dc=company,dc=local
LDAP_ADMIN_GROUP=cn=obstack-admins,ou=groups,dc=company,dc=local
```

### 1.2 Fichier `config/packages/security.yaml`

Éditez la section de sécurisation pour activer LDAP :

```yaml
security:
    providers:
        ldap_provider:
            ldap:
                service: Symfony\Component\Ldap\Ldap
                base_dn: '%env(LDAP_BASE_DN)%'
                search_dn: '%env(LDAP_BIND_DN)%'
                search_password: '%env(LDAP_BIND_PASSWORD)%'
                default_roles: ['ROLE_USER']
                uid_key: 'uid'
                filter: '(&(objectClass=person)(uid={username}))'

    firewalls:
        main:
            anonymous: false
            provider: ldap_provider
            form_login:
                login_path: login
                check_path: login
            logout:
                path: logout
                target: /

    role_hierarchy:
        ROLE_ADMIN: [ROLE_USER]
```

### 1.3 Suggestions de débogage

- Vérifier la connectivité LDAP :

```bash
ldapsearch -x -H ldap://ldap.company.local -D "cn=ldap-reader,dc=company,dc=local" -w changeme -b "ou=users,dc=company,dc=local" "(uid=test)"
```

- Tester la recherche du groupe admin :

```bash
ldapsearch -x -H ldap://ldap.company.local -D "cn=ldap-reader,dc=company,dc=local" -w changeme -b "ou=groups,dc=company,dc=local" "(memberUid=test)"
```

### 1.4 Gestion des rôles

- `ROLE_USER` : accès de base à l'application
- `ROLE_ADMIN` : accès administrateur
- `LDAP_ADMIN_GROUP` peut être utilisé pour mapper un groupe LDAP à un rôle admin

Dans le code, assurez-vous de mapper les groupes LDAP aux rôles Symfony correspondants.

---

## 2. Configuration OAuth2 / OpenID Connect

### 2.1 Variables d'environnement

```env
OAUTH2_PROVIDER_URL=https://idp.example.com
OAUTH2_CLIENT_ID=obstack-client
OAUTH2_CLIENT_SECRET=supersecret
OAUTH2_REDIRECT_URI=https://obstack.company.local/login/check-oauth
OAUTH2_SCOPE="openid profile email"
OAUTH2_USERINFO_ENDPOINT=https://idp.example.com/userinfo
OAUTH2_AUTHORIZATION_ENDPOINT=https://idp.example.com/authorize
OAUTH2_TOKEN_ENDPOINT=https://idp.example.com/token
```

### 2.2 Fichier `config/packages/security.yaml`

Ajouter ou modifier un firewall OAuth2 :

```yaml
security:
    providers:
        app_user_provider:
            entity:
                class: App\Entity\User
                property: email

    firewalls:
        main:
            anonymous: true
            oauth2_login:
                check_path: /login/check-oauth
                authorization_endpoint: '%env(OAUTH2_AUTHORIZATION_ENDPOINT)%'
                token_endpoint: '%env(OAUTH2_TOKEN_ENDPOINT)%'
                userinfo_endpoint: '%env(OAUTH2_USERINFO_ENDPOINT)%'
                client_id: '%env(OAUTH2_CLIENT_ID)%'
                client_secret: '%env(OAUTH2_CLIENT_SECRET)%'
                scope: '%env(OAUTH2_SCOPE)%'
                redirect_uri: '%env(OAUTH2_REDIRECT_URI)%'
                use_state: true
                user_provider: app_user_provider

            logout:
                path: /logout
                target: /

    access_control:
        - { path: ^/login$, roles: IS_AUTHENTICATED_ANONYMOUSLY }
        - { path: ^/admin, roles: ROLE_ADMIN }
        - { path: ^/, roles: ROLE_USER }
```

### 2.3 User Provider et mapping

Pour un fournisseur OAuth2/OIDC, utilisez un user provider qui crée ou met à jour l'utilisateur local après connexion.

#### Exemple de mapping des attributs :

- `email` -> `User.email`
- `name` -> `User.name`
- `groups` -> rôles Symfony

### 2.4 Redirection et callback

- `OAUTH2_REDIRECT_URI` doit correspondre à l'URL enregistrée auprès du provider.
- L'application doit gérer la route `/login/check-oauth`.

---

## 3. Vérification et tests

### Vérifier la configuration LDAP

```bash
php bin/console debug:config security
php bin/console debug:container Symfony\Component\Ldap\Ldap
```

### Vérifier la configuration OAuth2

- Tester la redirection vers le provider
- Vérifier le flux d'autorisation
- Vérifier la validation des tokens et des scopes

### Test utilisateur

1. Se connecter via le formulaire LDAP ou OAuth2
2. Vérifier les rôles attribués
3. Accéder à une page protégée (`/admin` ou `/settings`)

---

## 4. Bonnes pratiques

- Toujours chiffrer les secrets dans un vault ou un gestionnaire de secrets.
- Ne pas stocker les mots de passe LDAP en clair dans le dépôt.
- Activer HTTPS pour toutes les connexions OAuth2.
- Limiter les scopes OAuth2 au minimum nécessaire.
- Activer l'expiration de session et la rotation des jetons.

---

## 5. Exemples de fichiers `.env`

### LDAP

```env
LDAP_HOST=ldap.company.local
LDAP_PORT=389
LDAP_BASE_DN=dc=company,dc=local
LDAP_BIND_DN=cn=ldap-reader,dc=company,dc=local
LDAP_BIND_PASSWORD=changeme
LDAP_USER_BASE_DN=ou=users,dc=company,dc=local
LDAP_GROUP_BASE_DN=ou=groups,dc=company,dc=local
LDAP_ADMIN_GROUP=cn=obstack-admins,ou=groups,dc=company,dc=local
```

### OAuth2

```env
OAUTH2_PROVIDER_URL=https://idp.example.com
OAUTH2_CLIENT_ID=obstack-client
OAUTH2_CLIENT_SECRET=supersecret
OAUTH2_REDIRECT_URI=https://obstack.company.local/login/check-oauth
OAUTH2_SCOPE="openid profile email"
OAUTH2_USERINFO_ENDPOINT=https://idp.example.com/userinfo
OAUTH2_AUTHORIZATION_ENDPOINT=https://idp.example.com/authorize
OAUTH2_TOKEN_ENDPOINT=https://idp.example.com/token
```

---

## 6. Notes spécifiques

- Si l'authentification LDAP est activée, l'annuaire doit exposer les attributs utilisés pour la recherche d'utilisateur.
- Si OAuth2 est activé, veillez à configurer correctement le provider et les endpoints OIDC.
- La plateforme peut supporter les deux modes en parallèle, selon le firewall et le provider utilisé.
