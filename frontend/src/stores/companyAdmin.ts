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
