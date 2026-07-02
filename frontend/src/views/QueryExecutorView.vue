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

    <!-- Error Alert -->
    <div v-if="error" class="alert-error mb-4">
      {{ error }}
      <button @click="error = null" class="ml-2 font-bold">✕</button>
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
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <!-- Left: Sidebar with Templates & History -->
      <div class="space-y-4">
        <!-- Templates Panel -->
        <div class="card">
          <button
            @click="showTemplates = !showTemplates"
            class="w-full text-left font-semibold text-gray-900 flex justify-between items-center"
          >
            📋 Query Templates
            <span class="text-sm">{{ showTemplates ? '▼' : '▶' }}</span>
          </button>
          
          <div v-if="showTemplates" class="mt-4 space-y-2 max-h-64 overflow-y-auto">
            <div
              v-for="(template, idx) in templates"
              :key="idx"
              @click="applyTemplate(template)"
              class="p-2 bg-blue-50 rounded cursor-pointer hover:bg-blue-100 text-sm"
            >
              <p class="font-medium text-blue-900">{{ template.name }}</p>
              <p class="text-xs text-blue-700">{{ template.description }}</p>
            </div>
          </div>
        </div>

        <!-- History Panel -->
        <div class="card">
          <button
            @click="showHistory = !showHistory"
            class="w-full text-left font-semibold text-gray-900 flex justify-between items-center"
          >
            ⏱️ Query History
            <span class="text-sm">{{ showHistory ? '▼' : '▶' }}</span>
          </button>
          
          <div v-if="showHistory" class="mt-4 space-y-2 max-h-64 overflow-y-auto">
            <div v-if="queryHistory.length === 0" class="text-sm text-gray-600 py-4">
              No history yet
            </div>
            <div
              v-for="(item, idx) in queryHistory.slice(0, 20)"
              :key="idx"
              @click="loadHistoryQuery(item)"
              :class="{ 'bg-green-50': item.success, 'bg-red-50': !item.success }"
              class="p-2 rounded cursor-pointer hover:opacity-80 text-xs"
            >
              <p class="font-mono truncate">{{ item.query }}</p>
              <p class="text-gray-600">{{ formatTime(item.timestamp) }} - {{ item.duration }}ms</p>
            </div>
            <button
              v-if="queryHistory.length > 0"
              @click="clearHistory"
              class="btn btn-secondary text-xs w-full mt-2"
            >
              Clear History
            </button>
          </div>
        </div>

        <!-- Saved Queries -->
        <div class="card">
          <h3 class="font-semibold text-gray-900 mb-4">💾 Saved Queries</h3>
          
          <div v-if="savedQueries.length === 0" class="text-center py-6">
            <p class="text-sm text-gray-600">No saved queries yet</p>
          </div>

          <div v-else class="space-y-2 max-h-48 overflow-y-auto">
            <div
              v-for="(q, idx) in savedQueries"
              :key="idx"
              class="p-2 bg-gray-50 rounded text-sm group"
            >
              <div class="flex justify-between items-start">
                <div class="flex-1 cursor-pointer" @click="loadSavedQuery(q)">
                  <p class="font-medium text-gray-900 truncate hover:text-blue-600">{{ q.name }}</p>
                  <p class="text-xs text-gray-500 truncate">{{ q.query }}</p>
                </div>
                <button
                  @click="deleteSavedQuery(idx)"
                  class="text-red-500 opacity-0 group-hover:opacity-100 text-xs"
                >
                  ✕
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Query Editor & Results -->
      <div class="lg:col-span-3 space-y-4">
        <div class="card">
          <div class="flex justify-between items-center mb-4">
            <h3 class="font-semibold text-gray-900">Query Editor</h3>
            <span class="text-xs font-mono bg-gray-100 px-2 py-1 rounded">
              {{ queryType }} • Ctrl+Enter to execute
            </span>
          </div>
          
          <textarea
            v-model="query"
            placeholder="SELECT * FROM users LIMIT 10;"
            class="w-full h-56 p-3 font-mono text-sm border border-gray-300 rounded bg-gray-50 focus:bg-white focus:outline-none focus:border-blue-500 resize-none"
            @keydown.ctrl.enter="executeQuery"
            @keydown.meta.enter="executeQuery"
          ></textarea>

          <div class="mt-4 flex flex-wrap gap-2">
            <button
              :disabled="isExecuting || !query.trim()"
              @click="executeQuery"
              :class="{ 'opacity-50 cursor-not-allowed': isExecuting || !query.trim() }"
              class="btn btn-primary"
            >
              {{ isExecuting ? '⟳ Executing...' : '▶️ Execute (Ctrl+Enter)' }}
            </button>
            <button
              @click="saveQuery"
              :disabled="!query.trim()"
              class="btn btn-secondary"
            >
              💾 Save
            </button>
            <button
              @click="clearQuery"
              class="btn btn-secondary"
            >
              🗑️ Clear
            </button>
          </div>
        </div>
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
import { databaseService } from '@/services/database'

const route = useRoute()
const databaseStore = useDatabaseStore()

const connectionId = computed(() => parseInt(String(route.params.id)))
const selectedConnection = computed(() =>
  databaseStore.connections.find(c => c.id === connectionId.value)
)

const query = ref(route.query.table ? `SELECT * FROM ${route.query.table};` : '')
const results = ref<Record<string, any>[]>([])
const isExecuting = ref(false)
const error = ref<string | null>(null)
const executionStatus = ref<{ success: boolean; message: string; duration: number } | null>(null)
const executionTime = ref('0ms')
const queryHistory = ref<Array<{ query: string; timestamp: number; duration: number; success: boolean; rowCount: number }>>([])
const savedQueries = ref<{ name: string; query: string; type: string }[]>([])
const showHistory = ref(false)
const showTemplates = ref(false)
const selectedTemplate = ref<string | null>(null)

const queryType = computed(() => {
  const q = query.value.trim().toUpperCase()
  if (q.startsWith('SELECT')) return 'SELECT'
  if (q.startsWith('INSERT')) return 'INSERT'
  if (q.startsWith('UPDATE')) return 'UPDATE'
  if (q.startsWith('DELETE')) return 'DELETE'
  if (q.startsWith('WITH')) return 'CTE'
  return 'QUERY'
})

const resultColumns = computed(() => {
  if (results.value.length === 0) return []
  return Object.keys(results.value[0])
})

const templates = computed(() => {
  const dbType = selectedConnection.value?.type || 'postgresql'
  return QUERY_TEMPLATES[dbType as keyof typeof QUERY_TEMPLATES] || QUERY_TEMPLATES.postgresql
})

const QUERY_TEMPLATES = {
  postgresql: [
    { name: 'Select All', query: 'SELECT * FROM table_name LIMIT 10;', description: 'Fetch all rows from a table' },
    { name: 'Count Rows', query: 'SELECT COUNT(*) as total_rows FROM table_name;', description: 'Count total rows' },
    { name: 'Find by ID', query: 'SELECT * FROM table_name WHERE id = $1;', description: 'Find record by ID' },
    { name: 'Filter & Sort', query: 'SELECT * FROM table_name WHERE status = \'active\' ORDER BY created_at DESC LIMIT 50;', description: 'Filter and sort results' },
    { name: 'Join Tables', query: 'SELECT a.*, b.* FROM table_a a INNER JOIN table_b b ON a.id = b.a_id LIMIT 10;', description: 'Join two tables' },
    { name: 'Aggregate Data', query: 'SELECT category, COUNT(*) as count, AVG(amount) as avg FROM table_name GROUP BY category;', description: 'Group and aggregate data' },
  ],
  mysql: [
    { name: 'Select All', query: 'SELECT * FROM table_name LIMIT 10;', description: 'Fetch all rows from a table' },
    { name: 'Count Rows', query: 'SELECT COUNT(*) as total_rows FROM table_name;', description: 'Count total rows' },
    { name: 'Find by ID', query: 'SELECT * FROM table_name WHERE id = ?;', description: 'Find record by ID' },
    { name: 'Filter & Sort', query: 'SELECT * FROM table_name WHERE status = \'active\' ORDER BY created_at DESC LIMIT 50;', description: 'Filter and sort results' },
  ],
  neo4j: [
    { name: 'List Nodes', query: 'MATCH (n) RETURN n LIMIT 10;', description: 'List all nodes' },
    { name: 'Count Nodes', query: 'MATCH (n) RETURN COUNT(n) as total;', description: 'Count nodes' },
    { name: 'Find by Label', query: 'MATCH (n:Person) RETURN n LIMIT 10;', description: 'Find nodes by label' },
  ],
  arangodb: [
    { name: 'List Documents', query: 'FOR doc IN collection_name LIMIT 10 RETURN doc;', description: 'Fetch documents' },
    { name: 'Count Documents', query: 'RETURN LENGTH(collection_name);', description: 'Count documents' },
    { name: 'Filter Documents', query: 'FOR doc IN collection_name FILTER doc.status == "active" LIMIT 10 RETURN doc;', description: 'Filter documents' },
  ]
}

onMounted(() => {
  loadSavedQueries()
  loadQueryHistory()
  setupKeyboardShortcuts()
})

const setupKeyboardShortcuts = () => {
  window.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && !isExecuting.value && query.value.trim()) {
      executeQuery()
    }
  })
}

const executeQuery = async () => {
  if (!query.value.trim() || !selectedConnection.value) return

  isExecuting.value = true
  error.value = null
  const startTime = Date.now()

  try {
    const result = await databaseService.executeQuery(
      selectedConnection.value.id,
      query.value,
      []
    )

    if (Array.isArray(result.data)) {
      results.value = result.data
    }

    const duration = Date.now() - startTime
    const rowCount = Array.isArray(result.data) ? result.data.length : (result.affectedRows || 0)

    executionStatus.value = {
      success: true,
      message: `Query executed successfully. ${queryType.value === 'SELECT' ? rowCount + ' rows returned' : rowCount + ' rows affected'}.`,
      duration
    }

    executionTime.value = duration + 'ms'
    
    // Add to history
    queryHistory.value.unshift({
      query: query.value,
      timestamp: Date.now(),
      duration,
      success: true,
      rowCount
    })
    localStorage.setItem('queryHistory', JSON.stringify(queryHistory.value.slice(0, 50)))
  } catch (err: any) {
    error.value = err.message || 'Failed to execute query'
    executionStatus.value = {
      success: false,
      message: `Error: ${err.message}`,
      duration: Date.now() - startTime
    }
    
    // Add failed query to history
    queryHistory.value.unshift({
      query: query.value,
      timestamp: Date.now(),
      duration: Date.now() - startTime,
      success: false,
      rowCount: 0
    })
    console.error('Query execution error:', err)
  } finally {
    isExecuting.value = false
  }
}

const clearQuery = () => {
  query.value = ''
  results.value = []
  executionStatus.value = null
  error.value = null
}

const saveQuery = () => {
  if (!query.value.trim()) return

  const name = prompt('Enter query name:')
  if (name) {
    savedQueries.value.push({ 
      name, 
      query: query.value,
      type: queryType.value
    })
    localStorage.setItem('savedQueries', JSON.stringify(savedQueries.value))
  }
}

const loadSavedQuery = (q: { name: string; query: string; type: string }) => {
  query.value = q.query
  showTemplates.value = false
}

const loadHistoryQuery = (q: any) => {
  query.value = q.query
  showHistory.value = false
}

const applyTemplate = (template: any) => {
  query.value = template.query
  selectedTemplate.value = template.name
  showTemplates.value = false
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

const loadSavedQueries = () => {
  const saved = localStorage.getItem('savedQueries')
  if (saved) {
    try {
      savedQueries.value = JSON.parse(saved)
    } catch (e) {
      console.error('Failed to load saved queries', e)
    }
  }
}

const loadQueryHistory = () => {
  const history = localStorage.getItem('queryHistory')
  if (history) {
    try {
      queryHistory.value = JSON.parse(history)
    } catch (e) {
      console.error('Failed to load query history', e)
    }
  }
}

const clearHistory = () => {
  if (confirm('Clear all query history?')) {
    queryHistory.value = []
    localStorage.removeItem('queryHistory')
  }
}

const deleteSavedQuery = (index: number) => {
  savedQueries.value.splice(index, 1)
  localStorage.setItem('savedQueries', JSON.stringify(savedQueries.value))
}

const formatValue = (value: any) => {
  if (value === null) return 'NULL'
  if (typeof value === 'boolean') return value ? 'TRUE' : 'FALSE'
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}

const formatTime = (timestamp: number) => {
  return new Date(timestamp).toLocaleTimeString()
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
