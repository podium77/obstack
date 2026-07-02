import { apiClient } from './api'
import type { ApiResponse } from '@/types'

export interface PerformanceMetric {
  endpoint: string
  executions: number
  avgTime: number
  maxTime: number
  minTime: number
}

export interface SlowQuery {
  id: number
  endpoint: string
  executionTime: number
  query: string
  method: string
  timestamp: string
}

export interface DatabaseStats {
  driver: string
  tables: number
  totalSize: number
  status: string
  lastCheck: string
  error?: string
}

export interface ExecutionStat {
  period: string
  total: number
  successful: number
  failed: number
  avgTime: number
}

export interface UserActivity {
  userId: number
  actions: number
  lastAction: string
}

export interface TopEndpoint {
  endpoint: string
  method: string
  accessCount: number
  avgTime: number
}

export interface ErrorStat {
  endpoint: string
  count: number
  error: string
}

export interface PerformanceScore {
  score: number
  rating: 'excellent' | 'good' | 'fair' | 'poor' | 'critical'
  maxScore: number
}

export interface PerformanceDashboard {
  performanceScore: number
  metrics: PerformanceMetric[]
  slowQueries: SlowQuery[]
  databaseStats: DatabaseStats
  topEndpoints: TopEndpoint[]
  errorStats: ErrorStat[]
  executionStats: ExecutionStat[]
}

export const performanceService = {
  async getMetrics(hours = 24): Promise<PerformanceMetric[]> {
    try {
      const response = await apiClient.get<ApiResponse<PerformanceMetric[]>>(
        `/admin/performance/metrics?hours=${hours}`
      )
      return response.data?.data || []
    } catch (error: any) {
      throw new Error(error.response?.data?.error || 'Failed to fetch performance metrics')
    }
  },

  async getSlowQueries(threshold = 1000, limit = 50): Promise<SlowQuery[]> {
    try {
      const response = await apiClient.get<ApiResponse<SlowQuery[]>>(
        `/admin/performance/slow-queries?threshold=${threshold}&limit=${limit}`
      )
      return response.data?.data || []
    } catch (error: any) {
      throw new Error(error.response?.data?.error || 'Failed to fetch slow queries')
    }
  },

  async getDatabaseStats(): Promise<DatabaseStats> {
    try {
      const response = await apiClient.get<ApiResponse<DatabaseStats>>(
        '/admin/performance/database-stats'
      )
      return response.data?.data || {}
    } catch (error: any) {
      throw new Error(error.response?.data?.error || 'Failed to fetch database stats')
    }
  },

  async getExecutionStats(hours = 24, interval: 'hour' | 'day' = 'hour'): Promise<ExecutionStat[]> {
    try {
      const response = await apiClient.get<ApiResponse<ExecutionStat[]>>(
        `/admin/performance/execution-stats?hours=${hours}&interval=${interval}`
      )
      return response.data?.data || []
    } catch (error: any) {
      throw new Error(error.response?.data?.error || 'Failed to fetch execution stats')
    }
  },

  async getUserActivity(days = 7): Promise<UserActivity[]> {
    try {
      const response = await apiClient.get<ApiResponse<UserActivity[]>>(
        `/admin/performance/user-activity?days=${days}`
      )
      return response.data?.data || []
    } catch (error: any) {
      throw new Error(error.response?.data?.error || 'Failed to fetch user activity')
    }
  },

  async getTopEndpoints(limit = 20): Promise<TopEndpoint[]> {
    try {
      const response = await apiClient.get<ApiResponse<TopEndpoint[]>>(
        `/admin/performance/top-endpoints?limit=${limit}`
      )
      return response.data?.data || []
    } catch (error: any) {
      throw new Error(error.response?.data?.error || 'Failed to fetch top endpoints')
    }
  },

  async getErrors(hours = 24): Promise<ErrorStat[]> {
    try {
      const response = await apiClient.get<ApiResponse<ErrorStat[]>>(
        `/admin/performance/errors?hours=${hours}`
      )
      return response.data?.data || []
    } catch (error: any) {
      throw new Error(error.response?.data?.error || 'Failed to fetch error stats')
    }
  },

  async getPerformanceScore(): Promise<PerformanceScore> {
    try {
      const response = await apiClient.get<ApiResponse<PerformanceScore>>(
        '/admin/performance/score'
      )
      return response.data?.data || { score: 0, rating: 'critical', maxScore: 100 }
    } catch (error: any) {
      throw new Error(error.response?.data?.error || 'Failed to fetch performance score')
    }
  },

  async getDashboard(): Promise<PerformanceDashboard> {
    try {
      const response = await apiClient.get<ApiResponse<PerformanceDashboard>>(
        '/admin/performance/dashboard'
      )
      return response.data?.data || {}
    } catch (error: any) {
      throw new Error(error.response?.data?.error || 'Failed to fetch performance dashboard')
    }
  }
}
