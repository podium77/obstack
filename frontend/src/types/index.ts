export interface User {
  id: number
  email: string
  displayName: string
  isGlobalAdmin: boolean
  createdAt: string
}

export interface AuthTokens {
  token: string
  refreshToken?: string
  expiresAt?: string
}

export interface LoginCredentials {
  username: string
  password: string
}

export interface ApiResponse<T = any> {
  success: boolean
  data?: T
  message?: string
  error?: string
  details?: any
}

export interface DatabaseConnection {
  id: number
  name: string
  type: 'mysql' | 'postgresql' | 'neo4j' | 'arangodb'
  host: string
  port: number
  database: string
  username: string
  active: boolean
  tested: boolean
  lastTestedAt?: string
  createdAt?: string
  updatedAt?: string
}

export interface DatabaseStructure {
  schema: string
  name: string
  type: 'table' | 'view' | 'collection'
  columns?: string[]
  rowCount?: number
}

export interface QueryResult {
  columns: string[]
  rows: any[]
  affectedRows?: number
  executionTime?: number
}

export interface AuditLog {
  id: number
  action: string
  userId: number
  resourceType?: string
  resourceId?: number
  ipAddress: string
  userAgent: string
  httpMethod: string
  endpoint: string
  status: 'success' | 'failure' | 'partial'
  description: string
  oldValues?: any
  newValues?: any
  errorMessage?: string
  createdAt: string
}

export interface Role {
  id: number
  name: string
  description?: string
  permissions: Permission[]
  inheritedRoles?: Role[]
  createdAt?: string
}

export interface Permission {
  id: number
  code: string
  name: string
  description?: string
  scope: string
  category: string
  createdAt?: string
}
