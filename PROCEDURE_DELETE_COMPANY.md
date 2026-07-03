# Procédure de Suppression d'une Company (Entreprise)

## Vue d'ensemble
Cette procédure permet de supprimer une company via une interface Vue 3 avec confirmation sécurisée. La suppression est réservée aux SUPERADMIN et supprime en cascade tous les données associées (environnements, utilisateurs, applications, etc.).

---

## Architecture

### 1️⃣ Backend (Symfony)

#### 1.1 Créer l'API Endpoint

**Fichier:** `src/Controller/Admin/API/CompanyController.php`

```php
<?php
namespace App\Controller\Admin\API;

use App\Entity\Company;
use App\Repository\CompanyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/api/admin/companies', name: 'api_admin_companies_')]
#[IsGranted('ROLE_SUPERADMIN')]
class CompanyController extends AbstractController
{
    public function __construct(
        private readonly CompanyRepository $companyRepo,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Liste toutes les companies
     * GET /api/admin/companies
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $companies = $this->companyRepo->findAll();
        $data = array_map(fn(Company $c) => [
            'id'    => $c->getId(),
            'name'  => $c->getName(),
            'slug'  => $c->getSlug(),
            'active' => $c->isActive(),
            'created' => $c->getCreatedAt()?->format('Y-m-d H:i:s'),
        ], $companies);

        return $this->json(['companies' => $data]);
    }

    /**
     * Récupère une company spécifique
     * GET /api/admin/companies/{id}
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Company $company): JsonResponse
    {
        return $this->json([
            'id'    => $company->getId(),
            'name'  => $company->getName(),
            'slug'  => $company->getSlug(),
            'active' => $company->isActive(),
            'created' => $company->getCreatedAt()?->format('Y-m-d H:i:s'),
            'environments_count' => count($company->getEnvironments()),
            'users_count' => count($company->getCompanyUsers()),
        ]);
    }

    /**
     * Supprime une company
     * DELETE /api/admin/companies/{id}
     * 
     * ⚠️ DANGER: Supprime TOUT (environnements, applications, utilisateurs, etc.)
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Company $company): JsonResponse
    {
        try {
            $companyName = $company->getName();
            $companyId = $company->getId();

            // Vérification: ne pas supprimer la dernière company
            $totalCompanies = count($this->companyRepo->findAll());
            if ($totalCompanies === 1) {
                return $this->json([
                    'success' => false,
                    'error' => 'Impossible de supprimer la dernière company du système.'
                ], Response::HTTP_FORBIDDEN);
            }

            // Suppression en cascade (Doctrine gère les relations onDelete='CASCADE')
            $this->em->remove($company);
            $this->em->flush();

            return $this->json([
                'success' => true,
                'message' => "Company '{$companyName}' (ID: {$companyId}) supprimée avec succès.",
                'id' => $companyId,
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors de la suppression: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
```

#### 1.2 Mettre à jour les routes sécurisées
**Fichier:** `config/packages/security.yaml`

Assurer que les routes `/api/admin/` requièrent `ROLE_SUPERADMIN`:

```yaml
firewalls:
  api_admin:
    pattern: ^/api/admin/
    security: true
    stateless: true
    guard:
      authenticators:
        - jwt_authenticator
```

---

### 2️⃣ Frontend (Vue 3 + Pinia)

#### 2.1 Créer le Store Pinia

**Fichier:** `frontend/src/stores/companyAdmin.ts`

```typescript
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export interface Company {
  id: number
  name: string
  slug: string
  active: boolean
  created: string
  environments_count?: number
  users_count?: number
}

export const useCompanyAdminStore = defineStore('companyAdmin', () => {
  const companies = ref<Company[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const loadCompanies = async () => {
    loading.value = true
    error.value = null
    try {
      const response = await fetch('/api/admin/companies', {
        credentials: 'include'
      })
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`)
      }
      const data = await response.json()
      companies.value = data.companies || []
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Erreur de chargement'
    } finally {
      loading.value = false
    }
  }

  const deleteCompany = async (companyId: number): Promise<{ success: boolean; message?: string; error?: string }> => {
    loading.value = true
    error.value = null
    try {
      const response = await fetch(`/api/admin/companies/${companyId}`, {
        method: 'DELETE',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json'
        }
      })

      const data = await response.json()

      if (!response.ok) {
        error.value = data.error || 'Erreur lors de la suppression'
        return { success: false, error: error.value }
      }

      // Retirer la company de la liste locale
      companies.value = companies.value.filter(c => c.id !== companyId)

      return { success: true, message: data.message }
    } catch (e) {
      error.value = e instanceof Error ? e.message : 'Erreur réseau'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  return {
    companies: computed(() => companies.value),
    loading: computed(() => loading.value),
    error: computed(() => error.value),
    loadCompanies,
    deleteCompany
  }
})
```

#### 2.2 Créer le Composant Vue 3

**Fichier:** `frontend/src/views/CompanyManagementView.vue`

```vue
<template>
  <div class="company-management-page">
    <!-- Header -->
    <div class="page-header">
      <div class="header-content">
        <h1>
          <span class="icon">🏢</span>
          Gestion des Companies
        </h1>
        <p>Gérez les entreprises de la plateforme</p>
      </div>
    </div>

    <!-- Main Content -->
    <div class="page-container">
      <!-- Loading State -->
      <div v-if="store.loading.value" class="loading-state">
        <div class="spinner"></div>
        <p>Chargement des companies...</p>
      </div>

      <!-- Error State -->
      <div v-if="store.error.value" class="error-message">
        <span class="icon">⚠️</span>
        {{ store.error.value }}
      </div>

      <!-- Empty State -->
      <div v-if="!store.loading.value && store.companies.value.length === 0" class="empty-state">
        <p>Aucune company trouvée</p>
      </div>

      <!-- Companies Table -->
      <div v-if="!store.loading.value && store.companies.value.length > 0" class="companies-section">
        <div class="table-responsive">
          <table class="companies-table">
            <thead>
              <tr>
                <th>Nom</th>
                <th>Slug</th>
                <th>Environnements</th>
                <th>Utilisateurs</th>
                <th>Créée le</th>
                <th>Statut</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="company in store.companies.value" :key="company.id" class="company-row">
                <td class="company-name">{{ company.name }}</td>
                <td class="company-slug"><code>{{ company.slug }}</code></td>
                <td class="center">{{ company.environments_count || 0 }}</td>
                <td class="center">{{ company.users_count || 0 }}</td>
                <td>{{ formatDate(company.created) }}</td>
                <td class="center">
                  <span class="status-badge" :class="{ active: company.active, inactive: !company.active }">
                    {{ company.active ? 'Actif' : 'Inactif' }}
                  </span>
                </td>
                <td class="actions">
                  <button
                    @click="showDeleteConfirm(company)"
                    class="btn-delete"
                    title="Supprimer cette company"
                  >
                    🗑️ Supprimer
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="selectedCompanyToDelete" class="modal-overlay">
      <div class="modal-dialog">
        <div class="modal-header">
          <h2>⚠️ Supprimer une Company</h2>
          <button @click="closeDeleteConfirm" class="close-btn">&times;</button>
        </div>

        <div class="modal-body">
          <div class="warning-box">
            <p class="warning-title">🚨 ATTENTION - Cette action est IRRÉVERSIBLE</p>
            <p>Vous êtes sur le point de supprimer :</p>
            <div class="company-summary">
              <div class="summary-item">
                <span class="label">Company :</span>
                <span class="value">{{ selectedCompanyToDelete.name }}</span>
              </div>
              <div class="summary-item">
                <span class="label">Slug :</span>
                <code>{{ selectedCompanyToDelete.slug }}</code>
              </div>
              <div class="summary-item">
                <span class="label">Environnements :</span>
                <span class="value">{{ selectedCompanyToDelete.environments_count || 0 }}</span>
              </div>
              <div class="summary-item">
                <span class="label">Utilisateurs :</span>
                <span class="value">{{ selectedCompanyToDelete.users_count || 0 }}</span>
              </div>
            </div>

            <div class="consequences">
              <p class="section-title">Conséquences de la suppression :</p>
              <ul>
                <li>✗ Tous les environnements seront supprimés</li>
                <li>✗ Toutes les applications seront supprimées</li>
                <li>✗ Tous les utilisateurs de cette company seront supprimés</li>
                <li>✗ Tous les tokens d'agent seront supprimés</li>
                <li>✗ Tous les logs et métriques seront supprimés</li>
              </ul>
            </div>

            <!-- Confirmation Checkbox -->
            <label class="confirmation-check">
              <input
                v-model="confirmDelete"
                type="checkbox"
              />
              <span>Je comprends que tous les données de cette company seront supprimées définitivement</span>
            </label>
          </div>
        </div>

        <div class="modal-footer">
          <button
            @click="closeDeleteConfirm"
            class="btn-secondary"
          >
            Annuler
          </button>
          <button
            @click="confirmDeleteCompany"
            :disabled="!confirmDelete || isDeleting"
            class="btn-danger"
          >
            {{ isDeleting ? 'Suppression en cours...' : '🗑️ Supprimer Définitivement' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Success Message -->
    <div v-if="successMessage" class="success-message">
      ✓ {{ successMessage }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useCompanyAdminStore } from '@/stores/companyAdmin'
import type { Company } from '@/stores/companyAdmin'

const store = useCompanyAdminStore()
const selectedCompanyToDelete = ref<Company | null>(null)
const confirmDelete = ref(false)
const isDeleting = ref(false)
const successMessage = ref<string | null>(null)

onMounted(() => {
  store.loadCompanies()
})

const showDeleteConfirm = (company: Company) => {
  selectedCompanyToDelete.value = company
  confirmDelete.value = false
}

const closeDeleteConfirm = () => {
  selectedCompanyToDelete.value = null
  confirmDelete.value = false
}

const confirmDeleteCompany = async () => {
  if (!selectedCompanyToDelete.value) return

  isDeleting.value = true
  try {
    const result = await store.deleteCompany(selectedCompanyToDelete.value.id)
    
    if (result.success) {
      successMessage.value = result.message || 'Company supprimée avec succès'
      setTimeout(() => {
        successMessage.value = null
      }, 3000)
      closeDeleteConfirm()
    } else {
      // L'erreur est déjà dans store.error
    }
  } finally {
    isDeleting.value = false
  }
}

const formatDate = (date: string | undefined) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('fr-FR', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}
</script>

<style scoped lang="css">
.company-management-page {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  background: var(--color-bg);
}

.page-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 3rem 2rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.header-content h1 {
  margin: 0;
  font-size: 2rem;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.header-content p {
  margin: 0.5rem 0 0 0;
  opacity: 0.9;
  font-size: 1rem;
}

.page-container {
  flex: 1;
  padding: 2rem;
  max-width: 1200px;
  margin: 0 auto;
  width: 100%;
}

/* Loading, Error, Empty States */
.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  color: var(--color-text-secondary);
}

.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid var(--color-border);
  border-top-color: #667eea;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin-bottom: 1rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.error-message {
  background: #fee;
  border: 1px solid #fcc;
  border-left: 4px solid #f56565;
  color: #742a2a;
  padding: 1rem;
  border-radius: 4px;
  margin-bottom: 1.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

/* Table Styles */
.companies-section {
  background: var(--color-bg-secondary);
  border: 1px solid var(--color-border);
  border-radius: 8px;
  overflow: hidden;
}

.table-responsive {
  overflow-x: auto;
}

.companies-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.companies-table thead {
  background: var(--color-bg-tertiary);
  border-bottom: 2px solid var(--color-border);
}

.companies-table th {
  padding: 1rem;
  text-align: left;
  font-weight: 600;
  color: var(--color-text);
}

.companies-table td {
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--color-border);
}

.company-row:hover {
  background: var(--color-bg);
}

.company-name {
  font-weight: 500;
  color: #667eea;
}

.company-slug {
  font-family: 'Courier New', monospace;
  font-size: 0.85rem;
  color: var(--color-text-secondary);
}

.center {
  text-align: center;
}

.status-badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 500;
}

.status-badge.active {
  background: #d4edda;
  color: #155724;
}

.status-badge.inactive {
  background: #f8d7da;
  color: #721c24;
}

.actions {
  text-align: right;
}

.btn-delete {
  background: #f56565;
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9rem;
  transition: all 0.2s ease;
}

.btn-delete:hover {
  background: #e53e3e;
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(245, 101, 101, 0.3);
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 1rem;
}

.modal-dialog {
  background: var(--color-bg-secondary);
  border-radius: 8px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  max-width: 600px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid var(--color-border);
  background: var(--color-bg);
}

.modal-header h2 {
  margin: 0;
  font-size: 1.25rem;
  color: #f56565;
}

.close-btn {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: var(--color-text-secondary);
}

.close-btn:hover {
  color: var(--color-text);
}

.modal-body {
  padding: 1.5rem;
  flex: 1;
}

.warning-box {
  background: #fff5f5;
  border: 1px solid #feb2b2;
  border-radius: 6px;
  padding: 1rem;
}

.warning-title {
  color: #c53030;
  font-weight: 600;
  margin: 0 0 0.5rem 0;
}

.company-summary {
  background: var(--color-bg-secondary);
  border-radius: 4px;
  padding: 1rem;
  margin: 1rem 0;
  font-size: 0.9rem;
}

.summary-item {
  display: flex;
  justify-content: space-between;
  padding: 0.5rem 0;
}

.summary-item .label {
  font-weight: 500;
  color: var(--color-text-secondary);
}

.summary-item code {
  font-family: 'Courier New', monospace;
  color: #667eea;
}

.consequences {
  margin: 1.5rem 0;
}

.section-title {
  font-weight: 600;
  margin: 0.5rem 0;
  color: var(--color-text);
}

.consequences ul {
  margin: 0.5rem 0 0 0;
  padding-left: 1.5rem;
  color: var(--color-text);
}

.consequences li {
  margin: 0.25rem 0;
  color: #c53030;
}

.confirmation-check {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  margin-top: 1rem;
  padding: 1rem;
  background: var(--color-bg);
  border-radius: 4px;
  cursor: pointer;
}

.confirmation-check input[type="checkbox"] {
  margin-top: 0.25rem;
  cursor: pointer;
}

.confirmation-check span {
  font-size: 0.9rem;
  line-height: 1.4;
}

.modal-footer {
  display: flex;
  gap: 1rem;
  padding: 1rem 1.5rem;
  border-top: 1px solid var(--color-border);
  background: var(--color-bg);
}

.btn-secondary,
.btn-danger {
  flex: 1;
  padding: 0.75rem;
  border: none;
  border-radius: 4px;
  font-size: 0.9rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-secondary {
  background: var(--color-bg-tertiary);
  color: var(--color-text);
  border: 1px solid var(--color-border);
}

.btn-secondary:hover {
  background: var(--color-border);
}

.btn-danger {
  background: #f56565;
  color: white;
}

.btn-danger:hover:not(:disabled) {
  background: #e53e3e;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(245, 101, 101, 0.4);
}

.btn-danger:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* Success Message */
.success-message {
  position: fixed;
  top: 2rem;
  right: 2rem;
  background: #d4edda;
  color: #155724;
  padding: 1rem 1.5rem;
  border-radius: 4px;
  border: 1px solid #c3e6cb;
  border-left: 4px solid #28a745;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  z-index: 2000;
  animation: slideIn 0.3s ease;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateX(20px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

/* Responsive */
@media (max-width: 768px) {
  .page-header {
    padding: 2rem 1rem;
  }

  .header-content h1 {
    font-size: 1.5rem;
  }

  .page-container {
    padding: 1rem;
  }

  .companies-table {
    font-size: 0.8rem;
  }

  .companies-table th,
  .companies-table td {
    padding: 0.5rem;
  }

  .modal-dialog {
    max-width: 95vw;
  }
}
</style>
```

#### 2.3 Intégrer dans le Router

**Fichier:** `frontend/src/router/index.ts`

Ajouter la route:

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

---

## 📋 Étapes de Mise en Œuvre

### Phase 1: Backend (2-3 minutes)

1. ✅ Créer `src/Controller/Admin/API/CompanyController.php`
2. ✅ Implémenter les méthodes `list()`, `show()` et `delete()`
3. ✅ Tester les endpoints avec curl ou Postman:

```bash
# Lister les companies
curl -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  http://localhost:8000/api/admin/companies

# Supprimer une company
curl -X DELETE \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  http://localhost:8000/api/admin/companies/1
```

### Phase 2: Frontend Store (2-3 minutes)

1. ✅ Créer `frontend/src/stores/companyAdmin.ts`
2. ✅ Implémenter `loadCompanies()` et `deleteCompany()`
3. ✅ Vérifier que les appels API sont corrects

### Phase 3: Composant Vue 3 (5-10 minutes)

1. ✅ Créer `frontend/src/views/CompanyManagementView.vue`
2. ✅ Copier tous les styles et la logique de suppression
3. ✅ Tester l'interface localement

### Phase 4: Integration Routeur (1 minute)

1. ✅ Ajouter la route dans `frontend/src/router/index.ts`
2. ✅ Vérifier que la page est accessible à `/admin/companies`

### Phase 5: Build & Deploy (1-2 minutes)

1. ✅ `npm run build` dans le dossier frontend
2. ✅ Copier les assets compilés vers le serveur Symfony

---

## 🔒 Sécurité

- **Authentication:** Nécessite JWT token valide
- **Authorization:** Réservé aux `ROLE_SUPERADMIN`
- **Validation:** Vérification que c'est au moins 1 company avant suppression
- **Confirmation:** Modal avec checkbox de confirmation
- **Feedback:** Messages clairs sur les conséquences

---

## 🧪 Tests

### Test 1: Lister les companies

```bash
curl -H "Authorization: Bearer JWT_TOKEN" \
  http://localhost:8000/api/admin/companies
```

**Résultat attendu:**
```json
{
  "companies": [
    {
      "id": 1,
      "name": "Test Company",
      "slug": "test-company",
      "active": true,
      "created": "2026-07-03 10:30:00",
      "environments_count": 2,
      "users_count": 5
    }
  ]
}
```

### Test 2: Supprimer une company

```bash
curl -X DELETE \
  -H "Authorization: Bearer JWT_TOKEN" \
  http://localhost:8000/api/admin/companies/1
```

**Résultat attendu:**
```json
{
  "success": true,
  "message": "Company 'Test Company' (ID: 1) supprimée avec succès.",
  "id": 1
}
```

### Test 3: Erreur (dernière company)

```bash
curl -X DELETE \
  -H "Authorization: Bearer JWT_TOKEN" \
  http://localhost:8000/api/admin/companies/1
```

**Résultat attendu (si c'est la dernière):**
```json
{
  "success": false,
  "error": "Impossible de supprimer la dernière company du système."
}
```

---

## 📝 Notes

- Les suppressions sont **irréversibles** à cause de `onDelete='CASCADE'`
- Les environnements, applications, utilisateurs sont supprimés en cascade
- La base de données a des contraintes de clés étrangères
- Le Doctrine ORM gère automatiquement la cascade

---

## 🎯 Résumé

Vous avez maintenant une procédure complète pour:
1. **Backend:** API RESTful pour lister et supprimer des companies
2. **Frontend:** Interface Vue 3 avec confirmation sécurisée
3. **Store:** Gestion d'état avec Pinia
4. **Sécurité:** Authentification et autorisation
5. **UX:** Modal de confirmation avec détails et conséquences
