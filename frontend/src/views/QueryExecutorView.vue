<template>
  <div class="p-8">
    <!-- Page Title -->
    <div class="mb-8">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold text-gray-900">Query Executor</h1>
          <p class="text-gray-600 mt-2">Execute custom queries against your database</p>
        </div>
        <router-link :to="`/connections/${connectionId}`" class="btn btn-secondary">
          ← Back to Connection
        </router-link>
      </div>
    </div>

    <!-- Connection Info -->
    <div v-if="selectedConnection" class="card mb-6">
      <div class="flex justify-between items-center">
        <div>
          <h2 class="text-lg font-bold text-gray-900">{{ selectedConnection.name }}</h2>
          <p class="text-sm text-gray-600 mt-1">
            {{ selectedConnection.type }} - {{ selectedConnection.host }}:{{ selectedConnection.port }}
          </p>
        </div>
        <span class="text-xs font-mono bg-gray-100 px-2 py-1 rounded">
          {{ queryType }}
        </span>
      </div>
    </div>

    <!-- Query Editor -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Left: Query Editor -->
      <div class="space-y-4">
        <div class="card">
          <h3 class="font-semibold text-gray-900 mb-4">SQL Query</h3>
          
          <textarea
            v-model="query"
            placeholder="SELECT * FROM users LIMIT 10;"
            class="w-full h-48 p-3 font-mono text-sm border border-gray-300 rounded bg-gray-50 focus:bg-white focus:outline-none focus:border-blue-500"
          ></textarea>

          <div class="flex space-x-2 mt-4">
            <button
              :disabled="isExecuting || !query.trim()"
              @click="executeQuery"
              class="btn btn-primary"
            >
              {{ isExecuting ? '⟳ Executing...' : '▶️ Execute' }}
            </button>
            <button
              @click="clearQuery"
              class="btn btn-secondary"
            >
              🗑️ Clear
            </button>
            <button
              @click="saveQuery"
              class="btn btn-secondary"
            >
              💾 Save
            </button>
          </div>
        </div>

        <!-- Saved Queries -->
        <div class="card">
          <h3 class="font-semibold text-gray-900 mb-4">Saved Queries</h3>
          
          <div v-if="savedQueries.length === 0" class="text-center py-6">
            <p class="text-sm text-gray-600">No saved queries yet</p>
          </div>

          <div v-else class="space-y-2 max-h-48 overflow-y-auto">
            <div
              v-for="(q, idx) in savedQueries"
              :key="idx"
              class="p-2 bg-gray-50 rounded cursor-pointer hover:bg-gray-100"
              @click="loadSavedQuery(q)"
            >
              <p class="font-medium text-sm text-gray-900 truncate">{{ q.name }}</p>
              <p class="text-xs text-gray-500 truncate">{{ q.query }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Results -->
      <div class="space-y-4">
        <div class="card">
          <div class="flex justify-between items-center mb-4">
            <h3 class="font-semibold text-gray-900">Results</h3>
            <button
              v-if="results.length > 0"
              @click="exportResults"
              class="btn-secondary text-sm px-3 py-1"
            >
              📥 Export CSV
            </button>
          </div>

          <!-- Execution Status -->
          <div v-if="executionStatus" :class="{
            'bg-green-50 text-green-800': executionStatus.success,
            'bg-red-50 text-red-800': !executionStatus.success
          }" class="p-3 rounded mb-4 text-sm">
            {{ executionStatus.message }}
            <span v-if="executionStatus.duration" class="ml-2 text-xs opacity-75">
              ({{ executionStatus.duration }}ms)
            </span>
          </div>

          <!-- Results Table -->
          <div v-if="isExecuting" class="text-center py-8">
            <span class="spinner">⟳</span>
            <p class="text-gray-600 mt-4">Executing query...</p>
          </div>

          <div v-else-if="results.length === 0" class="text-center py-8">
            <p class="text-gray-600">No results yet. Execute a query to see results.</p>
          </div>

          <div v-else class="overflow-x-auto max-h-96">
            <table class="table text-sm">
              <thead>
                <tr>
                  <th v-for="(col, idx) in resultColumns" :key="idx" class="text-left">
                    {{ col }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, idx) in results.slice(0, 100)" :key="idx">
                  <td v-for="(col, cidx) in resultColumns" :key="cidx" class="truncate max-w-xs">
                    {{ formatValue(row[col]) }}
                  </td>
                </tr>
              </tbody>
            </table>

            <p v-if="results.length > 100" class="text-xs text-gray-500 mt-2 p-2">
              Showing 100 of {{ results.length }} rows
            </p>
          </div>
        </div>

        <!-- Query Info -->
        <div class="card">
          <h3 class="font-semibold text-gray-900 mb-4">Query Info</h3>
          
          <div class="space-y-3 text-sm">
            <div>
              <p class="text-gray-600">Query Type</p>
              <p class="font-mono text-blue-600">{{ queryType }}</p>
            </div>
            <div>
              <p class="text-gray-600">Rows Returned</p>
              <p class="font-bold">{{ results.length }}</p>
            </div>
            <div>
              <p class="text-gray-600">Execution Time</p>
              <p class="font-mono">{{ executionTime }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useDatabaseStore } from '@/stores/database'

const route = useRoute()
const databaseStore = useDatabaseStore()

const connectionId = computed(() => parseInt(String(route.params.id)))
const selectedConnection = computed(() =>
  databaseStore.connections.find(c => c.id === connectionId.value)
)

const query = ref(route.query.table ? `SELECT * FROM ${route.query.table};` : '')
const results = ref<Record<string, any>[]>([])
const isExecuting = ref(false)
const executionStatus = ref<{ success: boolean; message: string; duration: number } | null>(null)
const executionTime = ref('0ms')
const savedQueries = ref<{ name: string; query: string }[]>([
  { name: 'All Users', query: 'SELECT * FROM users;' },
  { name: 'User Count', query: 'SELECT COUNT(*) as count FROM users;' },
])

const queryType = computed(() => {
  const q = query.value.trim().toUpperCase()
  if (q.startsWith('SELECT')) return 'SELECT'
  if (q.startsWith('INSERT')) return 'INSERT'
  if (q.startsWith('UPDATE')) return 'UPDATE'
  if (q.startsWith('DELETE')) return 'DELETE'
  return 'QUERY'
})

const resultColumns = computed(() => {
  if (results.value.length === 0) return []
  return Object.keys(results.value[0])
})

onMounted(() => {
  // Load initial data
})

const executeQuery = async () => {
  if (!query.value.trim()) return

  isExecuting.value = true
  const startTime = Date.now()

  try {
    await new Promise(resolve => setTimeout(resolve, 800))

    // Mock query results
    if (query.value.toUpperCase().includes('SELECT')) {
      results.value = [
        { id: 1, name: 'User One', email: 'user1@example.com', created_at: '2026-01-01' },
        { id: 2, name: 'User Two', email: 'user2@example.com', created_at: '2026-01-02' },
        { id: 3, name: 'User Three', email: 'user3@example.com', created_at: '2026-01-03' },
      ]
      executionStatus.value = {
        success: true,
        message: `Query executed successfully. 3 rows returned.`,
        duration: Date.now() - startTime
      }
    } else {
      executionStatus.value = {
        success: true,
        message: `Query executed successfully.`,
        duration: Date.now() - startTime
      }
    }

    executionTime.value = (Date.now() - startTime) + 'ms'
  } catch (error) {
    executionStatus.value = {
      success: false,
      message: `Error: ${error}`,
      duration: Date.now() - startTime
    }
  } finally {
    isExecuting.value = false
  }
}

const clearQuery = () => {
  query.value = ''
  results.value = []
  executionStatus.value = null
}

const saveQuery = () => {
  if (!query.value.trim()) return

  const name = prompt('Enter query name:')
  if (name) {
    savedQueries.value.push({ name, query: query.value })
  }
}

const loadSavedQuery = (q: { name: string; query: string }) => {
  query.value = q.query
}

const exportResults = () => {
  if (results.value.length === 0) return

  const headers = resultColumns.value
  const csvContent = [
    headers.join(','),
    ...results.value.map(row =>
      headers.map(col => JSON.stringify(row[col])).join(',')
    )
  ].join('\n')

  const blob = new Blob([csvContent], { type: 'text/csv' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `query-results-${Date.now()}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

const formatValue = (value: any) => {
  if (value === null) return 'NULL'
  if (typeof value === 'boolean') return value ? 'TRUE' : 'FALSE'
  return String(value)
}
</script>

<style scoped>
.spinner {
  display: inline-block;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
