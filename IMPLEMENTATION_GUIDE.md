# Guide d'Intégration - Suppression de Company (Vue 3)

## 📋 Fichiers Créés

Trois fichiers ont été créés pour vous:

1. **Backend (PHP/Symfony):**
   - `src/Controller/Admin/API/CompanyController.php`

2. **Frontend (Vue 3 + TypeScript):**
   - `frontend/src/stores/companyAdmin.ts` (Pinia Store)
   - `frontend/src/views/CompanyManagementView.vue` (Component)

3. **Documentation:**
   - `PROCEDURE_DELETE_COMPANY.md` (Procédure complète)
   - `IMPLEMENTATION_GUIDE.md` (Ce fichier)

---

## ⚙️ Étapes d'Intégration

### ✅ Étape 1: Ajouter la Route (Frontend)

**Fichier:** `frontend/src/router/index.ts`

Ajouter cette route dans le tableau `routes`:

```typescript
{
  path: '/admin/companies',
  name: 'CompanyManagement',
  component: () => import('@/views/CompanyManagementView.vue'),
  meta: {
    requiresAuth: true,
    requiresRole: 'ROLE_SUPERADMIN',
    title: 'Gestion des Companies'
  }
}
```

### ✅ Étape 2: Lier dans le Navbar/Menu

**Fichier:** `frontend/src/components/Layout.vue` ou votre composant de navigation

Ajouter un lien vers la page de gestion:

```vue
<router-link to="/admin/companies" class="nav-item">
  🏢 Companies
</router-link>
```

### ✅ Étape 3: Vérifier la Sécurité (Backend)

**Fichier:** `config/packages/security.yaml`

Assurer que les routes API sont protégées:

```yaml
security:
  firewalls:
    api:
      pattern: ^/api/
      stateless: true
      jwt: ~
```

### ✅ Étape 4: Compiler et Tester

```bash
# Compiler le frontend
cd frontend
npm run build

# Redémarrer le serveur Symfony
cd ..
php -S localhost:8000 -t public
```

---

## 🧪 Tests Manuels

### Test 1: Accéder à la page

1. Allez à `http://localhost:5174/admin/companies`
2. Vous devez voir le tableau avec les companies

### Test 2: Afficher les données

1. Vérifiez que les companies se chargent
2. Vérifiez les colonnes: Nom, Slug, Environnements, Utilisateurs, Statut

### Test 3: Supprimer une company

1. Cliquez sur "🗑️ Supprimer"
2. Lisez les avertissements
3. Cochez la case de confirmation
4. Cliquez "Supprimer Définitivement"
5. Vérifiez que la company est supprimée

---

## 🔍 Débogage

### Erreur 401 Unauthorized

**Cause:** Le JWT token n'est pas valide ou absent

**Solution:**
```bash
# Vérifier que vous êtes connecté avec un SUPERADMIN
# Vérifier que le token JWT est dans les cookies
```

### Erreur 403 Forbidden

**Cause:** Vous n'avez pas la permission `ROLE_SUPERADMIN`

**Solution:**
```bash
# Vérifiez votre compte utilisateur
# Seuls les SUPERADMIN peuvent accéder
```

### Erreur 404 Not Found

**Cause:** L'endpoint n'existe pas

**Solution:**
```bash
# Vérifiez que le fichier CompanyController.php est créé
# Vérifiez que Symfony a reconnu la route
php bin/console debug:router | grep companies
```

### Données ne se chargent pas

**Cause:** Le store Pinia ne s'initialise pas correctement

**Solution:**
```typescript
// Dans CompanyManagementView.vue, vérifiez:
import { useCompanyAdminStore } from '@/stores/companyAdmin'

// La route doit être correct:
fetch('/api/admin/companies', { credentials: 'include' })
```

---

## 🔐 Sécurité

### Points importants:

1. **Authentification:**
   - Seul le JWT valide permet l'accès
   - Les cookies doivent avoir `secure` et `httpOnly`

2. **Autorisation:**
   - Seul `ROLE_SUPERADMIN` peut supprimer
   - La protection est au niveau du contrôleur

3. **Validation:**
   - Impossible de supprimer la dernière company
   - Confirmation obligatoire côté client

4. **Cascade:**
   - Doctrine gère `onDelete='CASCADE'`
   - Les données enfants sont automatiquement supprimées

---

## 📊 Architecture

```
┌─────────────────────────────────────┐
│     CompanyManagementView.vue        │  ← Interface utilisateur
│  (Affiche tableau + modal de conf)   │
└────────────────┬────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────┐
│    useCompanyAdminStore (Pinia)     │  ← Gestion d'état
│  (loadCompanies, deleteCompany)     │
└────────────────┬────────────────────┘
                 │
                 │ fetch('/api/admin/companies')
                 │
                 ▼
┌─────────────────────────────────────┐
│   CompanyController (Symfony)       │  ← API Backend
│  (GET /api/admin/companies/{id})    │
│  (DELETE /api/admin/companies/{id}) │
└────────────────┬────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────┐
│      CompanyRepository              │  ← Doctrine ORM
│      EntityManager                  │
└────────────────┬────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────┐
│      PostgreSQL Database            │  ← Données
│   (Companies, Environments, etc)    │
└─────────────────────────────────────┘
```

---

## 📝 Flux de Suppression

1. **Utilisateur clique "Supprimer"** → `showDeleteConfirm()`
2. **Modal s'affiche** → Affiche détails company + avertissements
3. **Utilisateur coche confirmation** → Active le bouton de suppression
4. **Utilisateur clique "Supprimer Définitivement"** → `confirmDeleteCompany()`
5. **Appel API** → `DELETE /api/admin/companies/{id}`
6. **Backend supprime** → Cascade Doctrine supprime les enfants
7. **Frontend met à jour** → Liste rafraîchie, message de succès

---

## 🎯 Cas d'Usage

### Utilisation 1: Admin local supprime sa company

```
Admin → Page /admin/companies → Clique Supprimer → Confirme → Company supprimée
```

### Utilisation 2: Superadmin global supprime une company

```
Superadmin Global → /admin/companies → Sélectionne une company → Supprime
```

### Utilisation 3: Migration de données

```
Export données → Supprime ancienne company → Import nouvelles données
```

---

## 🚀 Améliorations Futures

Vous pouvez ajouter:

1. **Historique de suppression:**
   - Enregistrer qui a supprimé et quand
   - Archiver au lieu de supprimer

2. **Suppression en lot:**
   - Interface pour sélectionner plusieurs companies

3. **Export avant suppression:**
   - Proposer d'exporter les données avant suppression

4. **Audit logging:**
   - Enregistrer tous les événements de suppression

5. **Archivage:**
   ```php
   $company->setActive(false);
   $company->setDeletedAt(new \DateTime());
   $em->flush();
   ```

---

## ✅ Checklist de Déploiement

- [ ] Fichier `CompanyController.php` créé
- [ ] Fichier `companyAdmin.ts` créé  
- [ ] Fichier `CompanyManagementView.vue` créé
- [ ] Route ajoutée dans `router/index.ts`
- [ ] Lien du navbar ajouté (optionnel)
- [ ] Tests manuels effectués
- [ ] `npm run build` réussi sans erreurs
- [ ] Serveur Symfony redémarré
- [ ] Testez l'accès avec un SUPERADMIN
- [ ] Testez la suppression d'une company de test

---

## 📞 Support

Pour des questions ou problèmes:

1. Vérifiez les logs Symfony:
   ```bash
   tail -f var/log/dev.log
   ```

2. Vérifiez la console du navigateur (F12)

3. Testez l'API directement:
   ```bash
   curl -H "Authorization: Bearer YOUR_TOKEN" \
     http://localhost:8000/api/admin/companies
   ```

---

**Voilà! Votre système de suppression de company est prêt à utiliser! 🎉**
