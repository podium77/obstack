import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { databaseService } from '@/services/database'
import type { DatabaseConnection } from '@/types'

export const useDatabaseStore = defineStore('database', () => {
  const connections = ref<DatabaseConnection[]>([])
  const selectedConnection = ref<DatabaseConnection | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  const connectionCount = computed(() => connections.value.length)
  const activeConnections = computed(() =>
    connections.value.filter(c => c.active)
  )
  const testedConnections = computed(() =>
    connections.value.filter(c => c.tested)
  )

  const loadConnections = async () => {
    isLoading.value = true
    error.value = null
    try {
      connections.value = await databaseService.listConnections()
    } catch (err: any) {
      error.value = err.message || 'Failed to load connections'
    } finally {
      isLoading.value = false
    }
  }

  const selectConnection = async (id: number) => {
    try {
      selectedConnection.value = await databaseService.getConnection(id)
    } catch (err: any) {
      error.value = err.message
    }
  }

  const addConnection = async (data: Partial<DatabaseConnection>) => {
    try {
      const newConnection = await databaseService.createConnection(data)
      connections.value.push(newConnection)
      return newConnection
    } catch (err: any) {
      error.value = err.message
      throw err
    }
  }

  const updateConnection = async (
    id: number,
    data: Partial<DatabaseConnection>
  ) => {
    try {
      const updated = await databaseService.updateConnection(id, data)
      const index = connections.value.findIndex(c => c.id === id)
      if (index !== -1) {
        connections.value[index] = updated
      }
      if (selectedConnection.value?.id === id) {
        selectedConnection.value = updated
      }
      return updated
    } catch (err: any) {
      error.value = err.message
      throw err
    }
  }

  const removeConnection = async (id: number) => {
    try {
      await databaseService.deleteConnection(id)
      connections.value = connections.value.filter(c => c.id !== id)
      if (selectedConnection.value?.id === id) {
        selectedConnection.value = null
      }
    } catch (err: any) {
      error.value = err.message
      throw err
    }
  }

  const testConnection = async (id: number) => {
    try {
      const result = await databaseService.testConnection(id)
      // Mettre à jour le statut
      const conn = connections.value.find(c => c.id === id)
      if (conn) {
        conn.tested = true
        conn.lastTestedAt = new Date().toISOString()
      }
      return result
    } catch (err: any) {
      error.value = err.message
      throw err
    }
  }

  return {
    connections,
    selectedConnection,
    isLoading,
    error,
    connectionCount,
    activeConnections,
    testedConnections,
    loadConnections,
    selectConnection,
    addConnection,
    updateConnection,
    removeConnection,
    testConnection
  }
})
