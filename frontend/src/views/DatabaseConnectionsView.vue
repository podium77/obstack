<template>
  <div class="p-8">
    <!-- Page Title & Actions -->
    <div class="flex justify-between items-center mb-8">
      <div>
        <h1 class="text-3xl font-bold text-gray-900">Database Connections</h1>
        <p class="text-gray-600 mt-2">Manage your database connections</p>
      </div>
      <button
        @click="showNewConnectionModal = true"
        class="btn-primary"
      >
        + New Connection
      </button>
    </div>

    <!-- Error Alert -->
    <div v-if="databaseStore.error" class="alert-error mb-4">
      {{ databaseStore.error }}
      <button @click="databaseStore.error = null" class="ml-2 font-bold">✕</button>
    </div>

    <!-- Loading State -->
    <div v-if="databaseStore.isLoading" class="text-center py-12">
      <span class="spinner text-2xl">⟳</span>
      <p class="text-gray-600 mt-4">Loading connections...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="databaseStore.connections.length === 0" class="card text-center py-12">
      <div class="text-4xl mb-4">🔌</div>
      <p class="text-gray-600 mb-4">No database connections configured</p>
      <button
        @click="showNewConnectionModal = true"
        class="btn-primary"
      >
        Create Your First Connection
      </button>
    </div>

    <!-- Connections Table -->
    <div v-else class="card overflow-x-auto">
      <table class="table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Type</th>
            <th>Host</th>
            <th>Port</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="conn in databaseStore.connections" :key="conn.id">
            <td class="font-medium">{{ conn.name }}</td>
            <td>
              <span class="badge badge-primary text-xs">
                {{ conn.type.toUpperCase() }}
              </span>
            </td>
            <td>{{ conn.host }}</td>
            <td class="font-mono text-sm">{{ conn.port }}</td>
            <td>
              <div class="flex space-x-2">
                <span v-if="conn.active" class="badge badge-success">Active</span>
                <span v-if="conn.tested" class="badge badge-primary">Tested</span>
                <span v-else class="badge badge-warning">Not Tested</span>
              </div>
            </td>
            <td>
              <div class="flex space-x-2">
                <button
                  @click="selectConnection(conn.id)"
                  class="btn btn-secondary text-xs py-1"
                  title="View details"
                >
                  View
                </button>
                <button
                  @click="testConnection(conn.id)"
                  :disabled="isTestingConnection === conn.id"
                  class="btn btn-secondary text-xs py-1"
                  title="Test connection"
                >
                  {{ isTestingConnection === conn.id ? 'Testing...' : 'Test' }}
                </button>
                <button
                  @click="showDeleteConfirm(conn.id)"
                  class="btn bg-red-100 text-red-600 hover:bg-red-200 text-xs py-1"
                  title="Delete connection"
                >
                  Delete
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- New Connection Modal -->
    <div v-if="showNewConnectionModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
        <div class="p-6">
          <h2 class="text-xl font-bold text-gray-900 mb-4">New Database Connection</h2>

          <form @submit.prevent="createConnection" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Connection Name</label>
              <input
                v-model="newConnectionForm.name"
                type="text"
                class="input"
                placeholder="Production DB"
                required
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Database Type</label>
              <select v-model="newConnectionForm.type" class="input" required>
                <option value="">Select a type...</option>
                <option value="postgresql">PostgreSQL</option>
                <option value="mysql">MySQL</option>
                <option value="neo4j">Neo4j</option>
                <option value="arangodb">ArangoDB</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Host</label>
              <input
                v-model="newConnectionForm.host"
                type="text"
                class="input"
                placeholder="localhost"
                required
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Port</label>
              <input
                v-model="newConnectionForm.port"
                type="number"
                class="input"
                placeholder="5432"
                required
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Database</label>
              <input
                v-model="newConnectionForm.database"
                type="text"
                class="input"
                placeholder="mydb"
                required
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
              <input
                v-model="newConnectionForm.username"
                type="text"
                class="input"
                placeholder="postgres"
                required
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
              <input
                v-model="newConnectionForm.password"
                type="password"
                class="input"
                placeholder="••••••••"
                required
              />
            </div>

            <div class="flex space-x-3 pt-4">
              <button
                type="submit"
                :disabled="isCreatingConnection"
                class="btn-primary flex-1"
              >
                {{ isCreatingConnection ? 'Creating...' : 'Create' }}
              </button>
              <button
                type="button"
                @click="closeNewConnectionModal"
                class="btn-secondary flex-1"
              >
                Cancel
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="deleteConfirmId" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg shadow-xl max-w-sm w-full mx-4">
        <div class="p-6">
          <h2 class="text-xl font-bold text-gray-900 mb-4">Delete Connection?</h2>
          <p class="text-gray-600 mb-6">
            Are you sure you want to delete this connection? This action cannot be undone.
          </p>
          <div class="flex space-x-3">
            <button
              @click="confirmDelete"
              :disabled="isDeletingConnection"
              class="btn-danger flex-1"
            >
              {{ isDeletingConnection ? 'Deleting...' : 'Delete' }}
            </button>
            <button
              @click="deleteConfirmId = null"
              class="btn-secondary flex-1"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useDatabaseStore } from '@/stores/database'
import { useRouter } from 'vue-router'
import type { DatabaseConnection } from '@/types'

const databaseStore = useDatabaseStore()
const router = useRouter()

const showNewConnectionModal = ref(false)
const deleteConfirmId = ref<number | null>(null)
const isCreatingConnection = ref(false)
const isDeletingConnection = ref(false)
const isTestingConnection = ref<number | null>(null)

const newConnectionForm = reactive({
  name: '',
  type: '',
  host: '',
  port: 5432,
  database: '',
  username: '',
  password: ''
})

onMounted(async () => {
  await databaseStore.loadConnections()
})

const createConnection = async () => {
  isCreatingConnection.value = true
  try {
    await databaseStore.addConnection(newConnectionForm)
    closeNewConnectionModal()
  } catch (error) {
    console.error('Failed to create connection:', error)
  } finally {
    isCreatingConnection.value = false
  }
}

const closeNewConnectionModal = () => {
  showNewConnectionModal.value = false
  Object.assign(newConnectionForm, {
    name: '',
    type: '',
    host: '',
    port: 5432,
    database: '',
    username: '',
    password: ''
  })
}

const selectConnection = (id: number) => {
  router.push(`/connections/${id}`)
}

const testConnection = async (id: number) => {
  isTestingConnection.value = id
  try {
    await databaseStore.testConnection(id)
  } finally {
    isTestingConnection.value = null
  }
}

const showDeleteConfirm = (id: number) => {
  deleteConfirmId.value = id
}

const confirmDelete = async () => {
  if (!deleteConfirmId.value) return
  isDeletingConnection.value = true
  try {
    await databaseStore.removeConnection(deleteConfirmId.value)
    deleteConfirmId.value = null
  } finally {
    isDeletingConnection.value = false
  }
}
</script>

<style scoped>
</style>
