<template>
  <div class="p-8">
    <!-- Page Title -->
    <div class="mb-8">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold text-gray-900">Database Browser</h1>
          <p class="text-gray-600 mt-2">Explore database structures and data</p>
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
        <span :class="{
          'badge-success': selectedConnection.active,
          'badge-warning': !selectedConnection.active
        }" class="badge">
          {{ selectedConnection.active ? 'Active' : 'Inactive' }}
        </span>
      </div>
    </div>

    <!-- Main Content -->
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <!-- Left Sidebar - Structures -->
      <div class="lg:col-span-1">
        <div class="card">
          <h3 class="font-semibold text-gray-900 mb-4">Schemas & Tables</h3>
          
          <div v-if="isLoadingStructures" class="text-center py-4">
            <span class="spinner">⟳</span>
            <p class="text-sm text-gray-600 mt-2">Loading structures...</p>
          </div>

          <div v-else-if="structures.length === 0" class="text-center py-4">
            <p class="text-sm text-gray-600">No structures found</p>
          </div>

          <div v-else class="space-y-2 max-h-96 overflow-y-auto">
            <div
              v-for="struct in structures"
              :key="`${struct.schema}-${struct.name}`"
              :class="{ 'bg-blue-50 border-l-4 border-blue-500': selectedStructure?.name === struct.name }"
              class="p-2 cursor-pointer hover:bg-gray-50 border-l-4 border-gray-200"
              @click="selectStructure(struct)"
            >
              <div class="flex items-center space-x-2">
                <span class="text-lg">
                  {{ struct.type === 'table' ? '📋' : struct.type === 'view' ? '👁️' : '📁' }}
                </span>
                <div class="flex-1 min-w-0">
                  <p class="font-medium text-sm text-gray-900 truncate">{{ struct.name }}</p>
                  <p v-if="struct.schema" class="text-xs text-gray-500">{{ struct.schema }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Refresh Button -->
          <button
            :disabled="isLoadingStructures"
            @click="loadStructures"
            class="btn btn-secondary w-full mt-4"
          >
            🔄 Refresh
          </button>
        </div>
      </div>

      <!-- Right Content - Data -->
      <div class="lg:col-span-3">
        <div v-if="!selectedStructure" class="card text-center py-12">
          <p class="text-gray-600">Select a table or view to preview data</p>
        </div>

        <div v-else class="space-y-4">
          <!-- Structure Info -->
          <div class="card">
            <h3 class="font-semibold text-gray-900 mb-4">{{ selectedStructure.name }}</h3>
            
            <div class="grid grid-cols-2 gap-4">
              <div>
                <p class="text-sm text-gray-600">Type</p>
                <p class="font-medium">{{ selectedStructure.type }}</p>
              </div>
              <div>
                <p class="text-sm text-gray-600">Rows</p>
                <p class="font-medium">{{ selectedStructure.rowCount || 'N/A' }}</p>
              </div>
            </div>

            <div v-if="selectedStructure.columns" class="mt-4">
              <p class="text-sm font-semibold text-gray-700 mb-2">Columns</p>
              <div class="space-y-2">
                <div
                  v-for="(col, idx) in selectedStructure.columns.slice(0, 10)"
                  :key="idx"
                  class="text-sm"
                >
                  <span class="font-mono text-blue-600">{{ col }}</span>
                </div>
                <p v-if="(selectedStructure.columns?.length || 0) > 10" class="text-xs text-gray-500">
                  ... and {{ (selectedStructure.columns?.length || 0) - 10 }} more columns
                </p>
              </div>
            </div>
          </div>

          <!-- Data Preview -->
          <div class="card">
            <div class="flex justify-between items-center mb-4">
              <h3 class="font-semibold text-gray-900">Data Preview</h3>
              <button
                :disabled="isLoadingData"
                @click="loadTableData"
                class="btn-secondary text-sm px-3 py-1"
              >
                🔄 Load Data
              </button>
            </div>

            <div v-if="isLoadingData" class="text-center py-8">
              <span class="spinner">⟳</span>
              <p class="text-gray-600 mt-4">Loading data...</p>
            </div>

            <div v-else-if="tableData.length === 0" class="text-center py-8">
              <p class="text-gray-600">No data found</p>
            </div>

            <div v-else class="overflow-x-auto">
              <table class="table text-sm">
                <thead>
                  <tr>
                    <th v-for="(col, idx) in dataColumns" :key="idx">{{ col }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, idx) in tableData.slice(0, 5)" :key="idx">
                    <td v-for="(col, cidx) in dataColumns" :key="cidx" class="truncate max-w-xs">
                      {{ row[col] }}
                    </td>
                  </tr>
                </tbody>
              </table>
              
              <p v-if="tableData.length > 5" class="text-xs text-gray-500 mt-2">
                Showing 5 of {{ tableData.length }} rows
              </p>
            </div>

            <!-- Action Buttons -->
            <div class="flex space-x-2 mt-4">
              <router-link
                v-if="connectionId && selectedStructure"
                :to="`/query/${connectionId}?table=${selectedStructure.name}`"
                class="btn btn-primary text-sm"
              >
                ▶️ Query This Table
              </router-link>
              <button class="btn btn-secondary text-sm">
                📥 Export
              </button>
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
import type { DatabaseStructure } from '@/types'

const route = useRoute()
const databaseStore = useDatabaseStore()

const connectionId = computed(() => parseInt(String(route.params.id)))
const selectedConnection = computed(() =>
  databaseStore.connections.find(c => c.id === connectionId.value)
)

const structures = ref<DatabaseStructure[]>([])
const selectedStructure = ref<DatabaseStructure | null>(null)
const isLoadingStructures = ref(false)
const isLoadingData = ref(false)
const error = ref<string | null>(null)
const tableData = ref<Record<string, any>[]>([])

const dataColumns = computed(() => {
  if (tableData.value.length === 0) return []
  return Object.keys(tableData.value[0])
})

onMounted(async () => {
  await loadStructures()
})

const loadStructures = async () => {
  isLoadingStructures.value = true
  error.value = null
  try {
    if (!selectedConnection.value) {
      throw new Error('Connection not found')
    }
    
    structures.value = await databaseService.listStructures(selectedConnection.value.id)
  } catch (err: any) {
    error.value = err.message || 'Failed to load database structures'
    console.error('Error loading structures:', err)
  } finally {
    isLoadingStructures.value = false
  }
}

const selectStructure = (struct: DatabaseStructure) => {
  selectedStructure.value = struct
  tableData.value = []
}

const loadTableData = async () => {
  if (!selectedStructure.value || !selectedConnection.value) return
  
  isLoadingData.value = true
  error.value = null
  try {
    const result = await databaseService.listTableData(
      selectedConnection.value.id,
      selectedStructure.value.name,
      50,
      0
    )
    tableData.value = result.data
  } catch (err: any) {
    error.value = err.message || 'Failed to load table data'
    console.error('Error loading data:', err)
  } finally {
    isLoadingData.value = false
  }
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
