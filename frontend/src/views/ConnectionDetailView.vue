<template>
  <div class="p-8">
    <div class="flex justify-between items-center mb-8">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">Connection Details</h1>
        <p class="text-gray-600 mt-2">Manage and explore your database connection</p>
      </div>
      <router-link to="/connections" class="btn btn-secondary">
        ← Back to Connections
      </router-link>
    </div>

    <div v-if="selectedConnection" class="space-y-6">
      <!-- Connection Info Card -->
      <div class="card">
        <div class="flex justify-between items-start mb-6">
          <div>
            <h2 class="text-2xl font-bold text-gray-900">{{ selectedConnection.name }}</h2>
            <p class="text-gray-600 mt-1">{{ selectedConnection.type | formatType }}</p>
          </div>
          <span :class="{
            'badge-success': selectedConnection.active,
            'badge-warning': !selectedConnection.active
          }" class="badge">
            {{ selectedConnection.active ? 'Active' : 'Inactive' }}
          </span>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
          <div>
            <p class="text-sm text-gray-600">Host</p>
            <p class="font-mono">{{ selectedConnection.host }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-600">Port</p>
            <p class="font-mono">{{ selectedConnection.port }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-600">Database</p>
            <p class="font-mono">{{ selectedConnection.database }}</p>
          </div>
          <div>
            <p class="text-sm text-gray-600">Type</p>
            <p class="font-mono uppercase">{{ selectedConnection.type }}</p>
          </div>
        </div>

        <div class="mt-6 pt-6 border-t">
          <p class="text-sm text-gray-600 mb-3">Created: {{ formatDate(selectedConnection.createdAt) }}</p>
          <p v-if="selectedConnection.tested" class="text-sm text-gray-600">
            Last tested: {{ formatDate(selectedConnection.lastTestedAt) }}
          </p>
        </div>
      </div>

      <!-- Actions Card -->
      <div class="card">
        <h3 class="font-semibold text-gray-900 mb-4">Actions</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <router-link
            :to="`/browser/${selectedConnection.id}`"
            class="btn btn-primary"
          >
            🔍 Browse Database
          </router-link>
          <router-link
            :to="`/query/${selectedConnection.id}`"
            class="btn btn-primary"
          >
            ▶️ Execute Query
          </router-link>
          <button
            @click="testConnection"
            :disabled="isTesting"
            class="btn btn-secondary"
          >
            {{ isTesting ? '⟳ Testing...' : '🧪 Test Connection' }}
          </button>
        </div>
      </div>

      <!-- Test Status Card -->
      <div v-if="testStatus" :class="{
        'bg-green-50 border-green-200': testStatus.success,
        'bg-red-50 border-red-200': !testStatus.success
      }" class="card border-l-4">
        <p :class="{
          'text-green-800': testStatus.success,
          'text-red-800': !testStatus.success
        }" class="font-medium">
          {{ testStatus.message }}
        </p>
      </div>
    </div>

    <div v-else class="card text-center py-12">
      <p class="text-gray-600">Connection not found</p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useDatabaseStore } from '@/stores/database'
import type { DatabaseConnection } from '@/types'

const route = useRoute()
const databaseStore = useDatabaseStore()

const isTesting = ref(false)
const testStatus = ref<{ success: boolean; message: string } | null>(null)

const selectedConnection = computed(() =>
  databaseStore.connections.find(c => c.id === parseInt(String(route.params.id)))
)

onMounted(async () => {
  const id = parseInt(route.params.id as string)
  if (id) {
    await databaseStore.selectConnection(id)
  }
})

const testConnection = async () => {
  if (!selectedConnection.value) return

  isTesting.value = true
  testStatus.value = null

  try {
    await databaseStore.testConnection(selectedConnection.value.id)
    testStatus.value = {
      success: true,
      message: '✓ Connection tested successfully!'
    }
  } catch (error: any) {
    testStatus.value = {
      success: false,
      message: `✗ Connection test failed: ${error.message}`
    }
  } finally {
    isTesting.value = false
  }
}

const formatDate = (dateString: string | undefined) => {
  if (!dateString) return 'N/A'
  return new Date(dateString).toLocaleString()
}
</script>
