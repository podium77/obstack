# ⚡ Quick Start - Supprimer une Company en 10 Minutes

## 🎯 Objectif
Implémenter une fonctionnalité de suppression de company en Vue 3 avec confirmation sécurisée.

---

## 📂 Fichiers à Utiliser

| Fichier | Destination | Action |
|---------|-------------|--------|
| `CompanyController.php` | `src/Controller/Admin/API/` | ✅ Copier |
| `companyAdmin.ts` | `frontend/src/stores/` | ✅ Copier |
| `CompanyManagementView.vue` | `frontend/src/views/` | ✅ Copier |
| `router/index.ts` | `frontend/src/` | ⚠️ Modifier |

---

## ⚙️ Configuration en 4 Étapes

### ÉTAPE 1: Backend (2 min)

**✅ Vérifier que le fichier existe:**
```bash
ls -la src/Controller/Admin/API/CompanyController.php
```

**✅ Redémarrer Symfony:**
```bash
# Terminal 1
php -S localhost:8000 -t public
```

**✅ Vérifier la route:**
```bash
php bin/console debug:router | grep companies
# Doit afficher:
# api_admin_companies_list    GET      /api/admin/companies
# api_admin_companies_show    GET      /api/admin/companies/{id}
# api_admin_companies_delete  DELETE   /api/admin/companies/{id}
```

---

### ÉTAPE 2: Store Frontend (2 min)

**✅ Copier le fichier:**
```bash
# Vérifier qu'il existe
ls -la frontend/src/stores/companyAdmin.ts
```

---

### ÉTAPE 3: Composant Vue (2 min)

**✅ Copier le fichier:**
```bash
# Vérifier qu'il existe
ls -la frontend/src/views/CompanyManagementView.vue
```

---

### ÉTAPE 4: Router (2 min)

**Ouvrir:** `frontend/src/router/index.ts`

**Ajouter l'import:**
```typescript
import CompanyManagementView from '@/views/CompanyManagementView.vue'
```

**Ajouter la route dans le tableau routes:**
```typescript
{
  path: '/admin/companies',
  name: 'CompanyManagement',
  component: CompanyManagementView,
  meta: {
    requiresAuth: true,
    requiresRole: 'ROLE_SUPERADMIN',
    title: 'Gestion des Companies'
  }
}
```

---

## 🔨 Build (2 min)

```bash
cd frontend
npm run build

# Attend la message:
# ✓ built in X.XXs
```

---

## ✅ Tests (3 min)

### Test 1: Accéder à la page
```bash
# Dans votre navigateur:
http://localhost:5174/admin/companies
```

**Résultat attendu:** 
- Page charge
- Tableau affiche les companies
- Bouton "Supprimer" visible

### Test 2: Tester l'API
```bash
# Obtenir le token JWT d'abord (console browser)
localStorage.getItem('token')  # Ou voir les cookies

# Dans le terminal:
export JWT_TOKEN='votre_token'

# Tester l'API
bash test_company_api.sh
```

### Test 3: Tester la suppression
1. Cliquer "Supprimer" sur une company
2. Voir le modal de confirmation
3. Cocher la checkbox
4. Cliquer "Supprimer Définitivement"
5. ✅ Company disparaît du tableau

---

## 🎉 C'est Fait!

Votre système de suppression de company est maintenant opérationnel! ✨

---

## 📋 Checklist Finale

- [ ] Backend fichier copié
- [ ] Store fichier copié
- [ ] Vue fichier copié
- [ ] Router modifié (import + route)
- [ ] `npm run build` réussi
- [ ] Page `/admin/companies` accessible
- [ ] Tableau se charge
- [ ] Bouton Supprimer fonctionne
- [ ] Modal s'affiche
- [ ] Suppression OK

---

## 🐛 Troubleshooting Rapide

| Problème | Solution |
|----------|----------|
| 404 Page not found | Vérifiez que la route est ajoutée |
| 401 Unauthorized | Vérifiez le token JWT |
| 403 Forbidden | Vérifiez que vous êtes SUPERADMIN |
| Build error | `npm install` puis `npm run build` |
| Component not found | Vérifiez l'import dans router |

---

## 🚀 Prochaines Étapes (Optionnel)

1. **Archivage** au lieu de suppression
2. **Suppression programmée** avec confirmation
3. **Backup automatique** avant suppression
4. **Audit logging** de qui a supprimé
5. **Suppression en lot** pour plusieurs companies

---

## 📞 Besoin d'Aide?

1. Consultez: **IMPLEMENTATION_GUIDE.md**
2. Consultez: **PROCEDURE_DELETE_COMPANY.md**
3. Regardez: **VISUAL_PREVIEW.md**
4. Exécutez: **test_company_api.sh**

---

**Temps total estimé: 10-15 minutes ⏱️**

**À vous de jouer! 🎯**
