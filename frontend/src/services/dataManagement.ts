import { apiClient } from './api'
import type { ApiResponse } from '@/types'

export interface ImportResult {
  success: boolean
  rowsImported: number
  rowsSkipped: number
  errors: string[]
  totalRows: number
}

export interface ExportStats {
  tableName: string
  rowCount: number
  estimatedSize: number
}

export interface TableStructure {
  name: string
  columns: Array<{
    name: string
    type: string
    length?: number
    nullable: boolean
    default?: string
  }>
  indexes: Array<{
    name: string
    columns: string[]
    unique: boolean
    primary: boolean
  }>
}

export interface ValidationResult {
  valid: boolean
  errors: string[]
  warnings: string[]
  linesChecked: number
}

export interface QualityMetrics {
  totalRows: number
  columns: {
    total: number
    nullable: number
    withDefault: number
  }
  nullability: Record<string, {
    nullCount: number
    nullPercentage: number
  }>
}

class DataManagementService {
  /**
   * Import data from CSV
   */
  async importCsv(
    content: string,
    tableName: string,
    options: Record<string, any> = {}
  ): Promise<ImportResult> {
    const response = await apiClient.post<ApiResponse<ImportResult>>('/admin/import/csv', {
      content,
      tableName,
      options,
    })
    return response.data?.data || { success: false, rowsImported: 0, rowsSkipped: 0, errors: [], totalRows: 0 }
  }

  /**
   * Import data from JSON
   */
  async importJson(
    content: string,
    tableName: string,
    options: Record<string, any> = {}
  ): Promise<ImportResult> {
    const response = await apiClient.post<ApiResponse<ImportResult>>('/admin/import/json', {
      content,
      tableName,
      options,
    })
    return response.data?.data || { success: false, rowsImported: 0, rowsSkipped: 0, errors: [], totalRows: 0 }
  }

  /**
   * Validate data before import
   */
  async validateImport(
    content: string,
    tableName: string,
    format: 'csv' | 'json' = 'csv',
    options: Record<string, any> = {}
  ): Promise<ValidationResult> {
    const response = await apiClient.post<ApiResponse<ValidationResult>>('/admin/import/validate', {
      content,
      tableName,
      format,
      options,
    })
    return response.data?.data || { valid: false, errors: [], warnings: [], linesChecked: 0 }
  }

  /**
   * Export data to CSV
   */
  async exportCsv(
    tableName: string,
    limit: number = 10000,
    offset: number = 0
  ): Promise<Blob> {
    const response = await apiClient.get('/admin/export/csv', {
      params: { table: tableName, limit, offset },
      responseType: 'blob',
    })
    return response.data
  }

  /**
   * Export data to JSON
   */
  async exportJson(
    tableName: string,
    limit: number = 10000,
    offset: number = 0
  ): Promise<Blob> {
    const response = await apiClient.get('/admin/export/json', {
      params: { table: tableName, limit, offset },
      responseType: 'blob',
    })
    return response.data
  }

  /**
   * Export data to JSONL
   */
  async exportJsonL(
    tableName: string,
    limit: number = 10000,
    offset: number = 0
  ): Promise<Blob> {
    const response = await apiClient.get('/admin/export/jsonl', {
      params: { table: tableName, limit, offset },
      responseType: 'blob',
    })
    return response.data
  }

  /**
   * Export data as Excel
   */
  async exportExcel(
    tableName: string,
    limit: number = 10000,
    offset: number = 0
  ): Promise<Blob> {
    const response = await apiClient.get('/admin/export/excel', {
      params: { table: tableName, limit, offset },
      responseType: 'blob',
    })
    return response.data
  }

  /**
   * Get table structure
   */
  async getTableStructure(tableName: string): Promise<TableStructure> {
    const response = await apiClient.get<ApiResponse<TableStructure>>('/admin/export/structure', {
      params: { table: tableName },
    })
    return response.data?.data || { name: tableName, columns: [], indexes: [] }
  }

  /**
   * Get table statistics
   */
  async getTableStats(tableName: string): Promise<ExportStats> {
    const response = await apiClient.get<ApiResponse<ExportStats>>('/admin/export/stats', {
      params: { table: tableName },
    })
    return response.data?.data || { tableName, rowCount: 0, estimatedSize: 0 }
  }

  /**
   * Bulk insert rows
   */
  async bulkInsert(
    tableName: string,
    rows: Record<string, any>[],
    batchSize: number = 1000
  ): Promise<ImportResult> {
    const response = await apiClient.post<ApiResponse<ImportResult>>('/admin/bulk/insert', {
      tableName,
      rows,
      batchSize,
    })
    return response.data?.data || { success: false, rowsImported: 0, rowsSkipped: 0, errors: [], totalRows: 0 }
  }

  /**
   * Bulk update rows
   */
  async bulkUpdate(
    tableName: string,
    updateData: Record<string, any>,
    conditions: Record<string, any>
  ): Promise<{ success: boolean; affected: number; message: string }> {
    const response = await apiClient.post<ApiResponse<any>>('/admin/bulk/update', {
      tableName,
      updateData,
      conditions,
    })
    return response.data?.data || { success: false, affected: 0, message: '' }
  }

  /**
   * Bulk delete rows
   */
  async bulkDelete(
    tableName: string,
    conditions: Record<string, any>,
    confirm: boolean = false
  ): Promise<{ success: boolean; affected: number; message: string }> {
    const response = await apiClient.post<ApiResponse<any>>('/admin/bulk/delete', {
      tableName,
      conditions,
      confirm,
    })
    return response.data?.data || { success: false, affected: 0, message: '' }
  }

  /**
   * Analyze data quality
   */
  async analyzeQuality(tableName: string): Promise<QualityMetrics> {
    const response = await apiClient.get<ApiResponse<QualityMetrics>>('/admin/data/quality', {
      params: { table: tableName },
    })
    return response.data?.data || { totalRows: 0, columns: { total: 0, nullable: 0, withDefault: 0 }, nullability: {} }
  }

  /**
   * Download file helper
   */
  downloadFile(blob: Blob, filename: string): void {
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = filename
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  }
}

export default new DataManagementService()
