<template>
  <div class="p-8">
    <!-- Page Title -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900">Audit Logs</h1>
      <p class="text-gray-600 mt-2">View all system operations and activities</p>
    </div>

    <!-- Filters -->
    <div class="card mb-6">
      <h3 class="font-semibold text-gray-900 mb-4">Filters</h3>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Action</label>
          <select v-model="filters.action" class="input">
            <option value="">All Actions</option>
            <option value="create">Create</option>
            <option value="update">Update</option>
            <option value="delete">Delete</option>
            <option value="database_query">Query</option>
            <option value="login">Login</option>
            <option value="access_denied">Access Denied</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
          <select v-model="filters.status" class="input">
            <option value="">All Status</option>
            <option value="success">Success</option>
            <option value="failure">Failure</option>
            <option value="partial">Partial</option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Limit</label>
          <input v-model="filters.limit" type="number" min="10" max="500" class="input" />
        </div>

        <div class="flex items-end">
          <button @click="loadLogs" class="btn-primary w-full">
            Apply Filters
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="text-center py-12">
      <span class="spinner text-2xl">⟳</span>
      <p class="text-gray-600 mt-4">Loading audit logs...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="logs.length === 0" class="card text-center py-12">
      <p class="text-gray-600">No audit logs found</p>
    </div>

    <!-- Logs Table -->
    <div v-else class="card overflow-x-auto">
      <table class="table">
        <thead>
          <tr>
            <th>Timestamp</th>
            <th>Action</th>
            <th>User</th>
            <th>Status</th>
            <th>Resource</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in logs" :key="log.id">
            <td class="text-sm font-mono">{{ formatDate(log.createdAt) }}</td>
            <td>
              <span class="badge badge-primary">{{ log.action }}</span>
            </td>
            <td>User #{{ log.userId }}</td>
            <td>
              <span
                :class="{
                  'badge-success': log.status === 'success',
                  'badge-error': log.status === 'failure',
                  'badge-warning': log.status === 'partial'
                }"
                class="badge"
              >
                {{ log.status }}
              </span>
            </td>
            <td v-if="log.resourceType">{{ log.resourceType }} #{{ log.resourceId }}</td>
            <td v-else>-</td>
            <td>
              <button
                @click="expandedLog = expandedLog === log.id ? null : log.id"
                class="text-blue-600 hover:text-blue-700 text-sm"
              >
                {{ expandedLog === log.id ? '↑ Hide' : '↓ Show' }}
              </button>
            </td>
          </tr>

          <!-- Expanded Details -->
          <tr v-for="log in logs" v-show="expandedLog === log.id" :key="`${log.id}-details`">
            <td colspan="6" class="bg-gray-50 p-4">
              <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <p class="text-gray-600">Endpoint:</p>
                  <code class="bg-white p-2 rounded">{{ log.endpoint }}</code>
                </div>
                <div>
                  <p class="text-gray-600">IP Address:</p>
                  <code class="bg-white p-2 rounded">{{ log.ipAddress }}</code>
                </div>
                <div v-if="log.oldValues" class="col-span-2">
                  <p class="text-gray-600">Old Values:</p>
                  <pre class="bg-white p-2 rounded text-xs overflow-auto">{{ JSON.stringify(log.oldValues, null, 2) }}</pre>
                </div>
                <div v-if="log.newValues" class="col-span-2">
                  <p class="text-gray-600">New Values:</p>
                  <pre class="bg-white p-2 rounded text-xs overflow-auto">{{ JSON.stringify(log.newValues, null, 2) }}</pre>
                </div>
                <div v-if="log.errorMessage" class="col-span-2">
                  <p class="text-gray-600">Error:</p>
                  <p class="bg-red-50 p-2 rounded text-red-900">{{ log.errorMessage }}</p>
                </div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="logs.length > 0" class="mt-4 flex justify-between items-center">
      <p class="text-sm text-gray-600">
        Showing {{ logs.length }} of {{ totalLogs }} logs
      </p>
      <div class="flex space-x-2">
        <button
          @click="previousPage"
          :disabled="currentOffset === 0"
          class="btn btn-secondary"
        >
          ← Previous
        </button>
        <button
          @click="nextPage"
          :disabled="currentOffset + filters.limit >= totalLogs"
          class="btn btn-secondary"
        >
          Next →
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { auditService } from '@/services/audit'
import { formatDate } from '@/utils/format'
import type { AuditLog } from '@/types'

const logs = ref<AuditLog[]>([])
const totalLogs = ref(0)
const isLoading = ref(false)
const expandedLog = ref<number | null>(null)
const currentOffset = ref(0)

const filters = reactive({
  action: '',
  status: '',
  limit: 50,
  offset: 0
})

onMounted(() => {
  loadLogs()
})

const loadLogs = async () => {
  isLoading.value = true
  try {
    const query: any = {
      limit: filters.limit,
      offset: currentOffset.value
    }
    if (filters.action) query.action = filters.action
    if (filters.status) query.status = filters.status

    const result = await auditService.listLogs(query)
    logs.value = result.data
    totalLogs.value = result.total
  } catch (error) {
    console.error('Failed to load audit logs:', error)
  } finally {
    isLoading.value = false
  }
}

const nextPage = () => {
  currentOffset.value += filters.limit
  loadLogs()
}

const previousPage = () => {
  currentOffset.value = Math.max(0, currentOffset.value - filters.limit)
  loadLogs()
}
</script>

<style scoped>
code {
  @apply font-mono text-xs;
}
</style>
