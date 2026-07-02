<template>
  <div class="p-8">
    <!-- Page Title -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900">Dashboard</h1>
      <p class="text-gray-600 mt-2">Welcome to Obstack Admin Console</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
      <div class="card">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-gray-500 text-sm">Database Connections</p>
            <p class="text-3xl font-bold text-gray-900">{{ databaseStore.connectionCount }}</p>
          </div>
          <div class="text-4xl">🔌</div>
        </div>
      </div>

      <div class="card">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-gray-500 text-sm">Active Connections</p>
            <p class="text-3xl font-bold text-green-600">{{ databaseStore.activeConnections.length }}</p>
          </div>
          <div class="text-4xl">✅</div>
        </div>
      </div>

      <div class="card">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-gray-500 text-sm">Tested Connections</p>
            <p class="text-3xl font-bold text-blue-600">{{ databaseStore.testedConnections.length }}</p>
          </div>
          <div class="text-4xl">🧪</div>
        </div>
      </div>

      <div class="card">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-gray-500 text-sm">Current User</p>
            <p class="text-lg font-bold text-gray-900">{{ authStore.user?.displayName }}</p>
            <p v-if="authStore.user?.isGlobalAdmin" class="text-xs text-purple-600">🔑 Global Admin</p>
          </div>
          <div class="text-4xl">👤</div>
        </div>
      </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Recent Connections -->
      <div class="card">
        <h2 class="text-xl font-bold text-gray-900 mb-4">Database Connections</h2>
        <div v-if="databaseStore.isLoading" class="text-center py-4">
          <span class="spinner">⟳</span> Loading...
        </div>
        <div v-else-if="databaseStore.connections.length === 0" class="text-center py-8 text-gray-500">
          <p class="mb-4">No database connections yet</p>
          <router-link to="/connections" class="btn btn-primary">
            Create Connection
          </router-link>
        </div>
        <div v-else class="space-y-2">
          <div
            v-for="conn in databaseStore.connections.slice(0, 5)"
            :key="conn.id"
            class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
          >
            <div>
              <p class="font-medium text-gray-900">{{ conn.name }}</p>
              <p class="text-xs text-gray-500">{{ conn.type }} • {{ conn.host }}:{{ conn.port }}</p>
            </div>
            <div class="flex items-center space-x-2">
              <span v-if="conn.active" class="badge badge-success">Active</span>
              <span v-if="conn.tested" class="badge badge-primary">Tested</span>
            </div>
          </div>
          <router-link to="/connections" class="block text-center text-blue-600 hover:text-blue-700 text-sm font-medium mt-4">
            View All →
          </router-link>
        </div>
      </div>

      <!-- Info Panel -->
      <div class="card">
        <h2 class="text-xl font-bold text-gray-900 mb-4">System Info</h2>
        <div class="space-y-3 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-600">Version:</span>
            <span class="font-medium">1.0.0</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">Environment:</span>
            <span class="font-medium">{{ environment }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">API Endpoint:</span>
            <span class="font-medium truncate">{{ apiUrl }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">Last Refresh:</span>
            <span class="font-medium">{{ lastRefresh }}</span>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-6 space-y-2">
          <button
            @click="refreshData"
            :disabled="isRefreshing"
            class="btn-primary w-full"
          >
            {{ isRefreshing ? 'Refreshing...' : 'Refresh Data' }}
          </button>
          <router-link to="/connections" class="btn btn-secondary w-full text-center">
            Manage Connections
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useDatabaseStore } from '@/stores/database'

const authStore = useAuthStore()
const databaseStore = useDatabaseStore()
const isRefreshing = ref(false)
const lastRefresh = ref('')

const environment = computed(() => import.meta.env.MODE || 'development')
const apiUrl = computed(() => import.meta.env.VITE_API_URL || 'http://localhost:8000/api')

onMounted(async () => {
  await loadData()
})

const loadData = async () => {
  try {
    await databaseStore.loadConnections()
    lastRefresh.value = new Date().toLocaleTimeString('fr-FR')
  } catch (error) {
    console.error('Failed to load dashboard data:', error)
  }
}

const refreshData = async () => {
  isRefreshing.value = true
  try {
    await loadData()
  } finally {
    isRefreshing.value = false
  }
}
</script>

<style scoped>
</style>
