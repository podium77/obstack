import { apiClient } from './api'
import type { AuditLog } from '@/types'

export interface AuditLogQuery {
  limit?: number
  offset?: number
  action?: string
  userId?: number
  status?: 'success' | 'failure' | 'partial'
  resourceType?: string
  resourceId?: number
}

export const auditService = {
  async listLogs(query: AuditLogQuery = {}): Promise<{
    data: AuditLog[]
    total: number
  }> {
    const params = new URLSearchParams()
    Object.entries(query).forEach(([key, value]) => {
      if (value !== undefined) {
        params.append(key, String(value))
      }
    })

    const response = await apiClient.get<{
      data: AuditLog[]
      metadata: { total: number }
    }>(`/admin/audit/logs?${params.toString()}`)

    return {
      data: response.data || [],
      total: response.metadata?.total || 0
    }
  },

  async getUserActivity(userId: number): Promise<AuditLog[]> {
    const response = await apiClient.get<AuditLog[]>(
      `/admin/audit/user/${userId}`
    )
    return response.data || []
  },

  async getAccessDenied(hours: number = 24): Promise<AuditLog[]> {
    const response = await apiClient.get<AuditLog[]>(
      `/admin/audit/access-denied?hours=${hours}`
    )
    return response.data || []
  },

  async getResourceHistory(
    resourceType: string,
    resourceId: number
  ): Promise<AuditLog[]> {
    const response = await apiClient.get<AuditLog[]>(
      `/admin/audit/resource/${resourceType}/${resourceId}`
    )
    return response.data || []
  }
}
