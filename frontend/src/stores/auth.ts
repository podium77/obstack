import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authService } from '@/services/auth'
import type { User, LoginCredentials } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string>(localStorage.getItem('token') || '')
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  const isAuthenticated = computed(() => !!token.value && !!user.value)

  const login = async (credentials: LoginCredentials) => {
    isLoading.value = true
    error.value = null
    try {
      const response = await authService.login(credentials)
      token.value = response.token
      localStorage.setItem('token', response.token)
      
      // Valider le token pour récupérer les données utilisateur
      user.value = await authService.validateToken(response.token)
    } catch (err: any) {
      error.value = err.message || 'Login failed'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  const validateToken = async () => {
    if (!token.value) return
    
    try {
      user.value = await authService.validateToken(token.value)
    } catch (err) {
      logout()
    }
  }

  const logout = () => {
    user.value = null
    token.value = ''
    localStorage.removeItem('token')
    authService.logout()
  }

  return {
    user,
    token,
    isLoading,
    error,
    isAuthenticated,
    login,
    validateToken,
    logout
  }
})
