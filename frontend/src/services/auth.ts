import { apiClient } from './api'
import { mockAuthService } from './mock'
import type { User, LoginCredentials, AuthTokens, ApiResponse } from '@/types'

// Utiliser le mock en développement (quand VITE_USE_MOCK_API=true)
const USE_MOCK_API = import.meta.env.VITE_USE_MOCK_API === 'true'

export const authService = {
  async login(credentials: LoginCredentials): Promise<AuthTokens> {
    if (USE_MOCK_API) {
      return mockAuthService.login(credentials)
    }

    const response = await apiClient.post<AuthTokens>('/login', credentials)
    if (response.success && response.data) {
      return response.data
    }
    throw new Error(response.error || 'Login failed')
  },

  async validateToken(token: string): Promise<User> {
    if (USE_MOCK_API) {
      return mockAuthService.validateToken(token)
    }

    const response = await apiClient.get<User>('/admin/validate-token')
    if (response.success && response.data) {
      return response.data
    }
    throw new Error(response.error || 'Token validation failed')
  },

  async logout(): Promise<void> {
    if (USE_MOCK_API) {
      return mockAuthService.logout()
    }

    try {
      await apiClient.post('/logout')
    } catch (error) {
      console.error('Logout error:', error)
    }
  }
}
