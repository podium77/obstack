import { apiClient } from './api'
import type { DatabaseConnection, ApiResponse } from '@/types'

export const databaseService = {
  // Connexions
  async listConnections(): Promise<DatabaseConnection[]> {
    const response = await apiClient.get<DatabaseConnection[]>(
      '/admin/database-connections'
    )
    return response.data || []
  },

  async getConnection(id: number): Promise<DatabaseConnection> {
    const response = await apiClient.get<DatabaseConnection>(
      `/admin/database-connections/${id}`
    )
    if (response.success && response.data) {
      return response.data
    }
    throw new Error(response.error || 'Failed to fetch connection')
  },

  async createConnection(data: Partial<DatabaseConnection>): Promise<DatabaseConnection> {
    const response = await apiClient.post<DatabaseConnection>(
      '/admin/database-connections',
      data
    )
    if (response.success && response.data) {
      return response.data
    }
    throw new Error(response.error || 'Failed to create connection')
  },

  async updateConnection(
    id: number,
    data: Partial<DatabaseConnection>
  ): Promise<DatabaseConnection> {
    const response = await apiClient.put<DatabaseConnection>(
      `/admin/database-connections/${id}`,
      data
    )
    if (response.success && response.data) {
      return response.data
    }
    throw new Error(response.error || 'Failed to update connection')
  },

  async deleteConnection(id: number): Promise<void> {
    const response = await apiClient.delete(`/admin/database-connections/${id}`)
    if (!response.success) {
      throw new Error(response.error || 'Failed to delete connection')
    }
  },

  async testConnection(id: number): Promise<{ success: boolean; message: string }> {
    const response = await apiClient.post(
      `/admin/database-connections/${id}/test`
    )
    if (response.success) {
      return { success: true, message: response.message || 'Connection successful' }
    }
    throw new Error(response.error || 'Connection test failed')
  },

  // Database Browser Operations
  async listStructures(connectionId: number): Promise<any[]> {
    const response = await apiClient.get<any[]>(
      `/admin/database/${connectionId}/structures`
    )
    if (response.success && response.data) {
      return response.data
    }
    throw new Error(response.error || 'Failed to list structures')
  },

  async listTableData(connectionId: number, tableName: string, limit = 50, offset = 0): Promise<{ data: any[]; metadata: any }> {
    const response = await apiClient.get<any>(
      `/admin/database/${connectionId}/data?structure=${encodeURIComponent(tableName)}&limit=${limit}&offset=${offset}`
    )
    if (response.success) {
      return {
        data: response.data || [],
        metadata: response.metadata || { limit, offset, count: 0 }
      }
    }
    throw new Error(response.error || 'Failed to load table data')
  },

  async executeQuery(connectionId: number, query: string, params: any[] = []): Promise<{ data: any; affectedRows?: number }> {
    const response = await apiClient.post<any>(
      `/admin/database/${connectionId}/query`,
      { query, params }
    )
    if (response.success) {
      return {
        data: response.data || [],
        affectedRows: response.affectedRows
      }
    }
    throw new Error(response.error || 'Failed to execute query')
  }
}
