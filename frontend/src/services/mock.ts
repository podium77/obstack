import type { LoginCredentials, AuthTokens, User } from '@/types'

/**
 * Service d'authentification Mock pour le développement/test
 * Simule les réponses de l'API backend sans avoir besoin du backend complet
 */
const DEMO_TOKEN = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJvYnN0YWNrIiwic3ViIjoiMSIsImlhdCI6MTYxNjIzOTAyMiwiZXhwIjo5OTk5OTk5OTk5LCJlbWFpbCI6ImFkbWluQG9ic3RhY2subG9jYWwiLCJkaXNwbGF5TmFtZSI6IkFkbWluIFVzZXIiLCJyb2xlcyI6WyJST0xFX1VTRVIiLCJST0xFX0FETUlOIiwiUk9MRV9HTE9CQUxfQURNSU4iXX0.mock'

const DEMO_USER: User = {
  id: 1,
  email: 'admin@obstack.local',
  displayName: 'Admin User',
  isGlobalAdmin: true,
  createdAt: new Date().toISOString()
}

/**
 * Mock du service d'authentification API
 */
export const mockAuthService = {
  /**
   * Mock login - valide les credentials de démo
   */
  async login(credentials: LoginCredentials): Promise<AuthTokens> {
    // Simuler un délai réseau
    await new Promise(resolve => setTimeout(resolve, 500))

    // Accepter les credentials de démo
    if (
      (credentials.username === 'admin' || credentials.username === 'admin@obstack.local') &&
      (credentials.password === 'TestPassword123' || credentials.password === 'test')
    ) {
      return {
        token: DEMO_TOKEN,
        refreshToken: 'mock-refresh-token-' + Date.now(),
        expiresAt: new Date(Date.now() + 3600000).toISOString()
      }
    }

    // Rejeter les autres credentials
    throw new Error('Credentials invalides. Utilisez admin/TestPassword123')
  },

  /**
   * Mock validateToken - retourne l'utilisateur
   */
  async validateToken(token: string): Promise<User> {
    await new Promise(resolve => setTimeout(resolve, 300))

    if (token.includes('mock')) {
      return DEMO_USER
    }

    throw new Error('Token invalide')
  },

  /**
   * Mock logout - ne fait rien
   */
  async logout(): Promise<void> {
    await new Promise(resolve => setTimeout(resolve, 200))
    // No-op
  }
}
