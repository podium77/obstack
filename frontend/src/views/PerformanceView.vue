<template>
  <div class="p-8">
    <!-- Page Header -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900">Performance & Monitoring</h1>
      <p class="text-gray-600 mt-2">System performance metrics and analytics</p>
    </div>

    <!-- Performance Score Card -->
    <div class="mb-8">
      <div class="card">
        <div class="flex justify-between items-start">
          <div>
            <h2 class="text-lg font-semibold text-gray-900 mb-4">System Performance Score</h2>
            <div class="flex items-baseline gap-4">
              <span class="text-6xl font-bold" :class="scoreColor">{{ performanceScore.score }}</span>
              <div>
                <p class="text-sm text-gray-600">Rating</p>
                <p class="text-lg font-semibold" :class="ratingColor">{{ performanceScore.rating }}</p>
              </div>
            </div>
          </div>
          <div class="w-48 h-48">
            <svg class="w-full h-full" viewBox="0 0 200 200">
              <!-- Circular progress -->
              <circle cx="100" cy="100" r="90" fill="none" stroke="#e5e7eb" stroke-width="8" />
              <circle
                cx="100"
                cy="100"
                r="90"
                fill="none"
                :stroke="scoreColor.replace('text-', 'rgb(')"
                stroke-width="8"
                stroke-dasharray="565"
                :stroke-dashoffset="565 * (1 - performanceScore.score / 100)"
                stroke-linecap="round"
                transform="rotate(-90 100 100)"
              />
            </svg>
          </div>
        </div>
      </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <!-- Database Stats -->
      <div class="card" v-if="databaseStats">
        <h3 class="text-sm font-semibold text-gray-600 uppercase">Database</h3>
        <p class="text-2xl font-bold text-gray-900 mt-2">{{ databaseStats.tables }}</p>
        <p class="text-xs text-gray-500 mt-1">Tables</p>
        <p class="text-xs text-gray-500 mt-2">{{ formatBytes(databaseStats.totalSize) }}</p>
        <span v-if="databaseStats.status === 'connected'" class="inline-block mt-2 px-2 py-1 bg-green-100 text-green-800 text-xs rounded">Connected</span>
        <span v-else class="inline-block mt-2 px-2 py-1 bg-red-100 text-red-800 text-xs rounded">{{ databaseStats.status }}</span>
      </div>

      <!-- Queries Stats -->
      <div class="card" v-if="metricsTotal">
        <h3 class="text-sm font-semibold text-gray-600 uppercase">Top Query</h3>
        <p class="text-2xl font-bold text-gray-900 mt-2">{{ metricsTotal }}</p>
        <p class="text-xs text-gray-500 mt-1">Executions (24h)</p>
        <p class="text-xs text-gray-500 mt-2">Avg: {{ avgQueryTime }}ms</p>
      </div>

      <!-- Slow Queries Stats -->
      <div class="card">
        <h3 class="text-sm font-semibold text-gray-600 uppercase">Slow Queries</h3>
        <p class="text-2xl font-bold text-gray-900 mt-2">{{ slowQueries.length }}</p>
        <p class="text-xs text-gray-500 mt-1">Over 1s threshold</p>
        <p class="text-xs text-gray-500 mt-2">Fastest: {{ minSlowTime }}ms</p>
      </div>

      <!-- Errors Stats -->
      <div class="card">
        <h3 class="text-sm font-semibold text-gray-600 uppercase">Errors</h3>
        <p class="text-2xl font-bold text-gray-900 mt-2">{{ totalErrors }}</p>
        <p class="text-xs text-gray-500 mt-1">In 24 hours</p>
        <p class="text-xs text-gray-500 mt-2">Most: {{ mostCommonError }}</p>
      </div>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
      <!-- Top Endpoints -->
      <div class="lg:col-span-1">
        <div class="card">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Top Endpoints</h2>
          
          <div v-if="topEndpoints.length === 0" class="text-center py-6">
            <p class="text-gray-500">No endpoint data</p>
          </div>

          <div v-else class="space-y-3">
            <div v-for="(ep, idx) in topEndpoints.slice(0, 10)" :key="idx" class="border-b pb-3 last:border-b-0">
              <div class="flex justify-between items-start">
                <div class="flex-1">
                  <p class="font-mono text-xs text-gray-900 truncate">{{ ep.endpoint }}</p>
                  <p class="text-xs text-gray-500 mt-1">{{ ep.method }}</p>
                </div>
                <span class="text-xs font-bold text-blue-600">{{ ep.accessCount }}</span>
              </div>
              <p class="text-xs text-gray-500 mt-1">Avg: {{ ep.avgTime }}ms</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Slow Queries -->
      <div class="lg:col-span-2">
        <div class="card">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Slowest Queries</h2>
          
          <div v-if="slowQueries.length === 0" class="text-center py-6">
            <p class="text-gray-500">No slow queries detected</p>
          </div>

          <div v-else class="overflow-x-auto">
            <table class="table text-sm">
              <thead>
                <tr>
                  <th>Query</th>
                  <th>Time</th>
                  <th>Method</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(query, idx) in slowQueries.slice(0, 10)" :key="idx">
                  <td class="font-mono text-xs truncate max-w-xs">{{ query.query }}</td>
                  <td class="font-bold" :class="query.executionTime > 5000 ? 'text-red-600' : 'text-orange-600'">
                    {{ query.executionTime }}ms
                  </td>
                  <td class="text-xs">{{ query.method }}</td>
                  <td class="text-xs text-gray-500">{{ formatTime(query.timestamp) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Error Statistics -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
      <!-- Error By Endpoint -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Errors by Endpoint</h2>
        
        <div v-if="errorStats.length === 0" class="text-center py-6">
          <p class="text-green-600">No errors in 24 hours</p>
        </div>

        <div v-else class="space-y-3">
          <div v-for="(error, idx) in errorStats.slice(0, 8)" :key="idx" class="border-b pb-3 last:border-b-0">
            <div class="flex justify-between items-start">
              <div class="flex-1">
                <p class="font-mono text-xs text-gray-900 truncate">{{ error.endpoint }}</p>
                <p class="text-xs text-red-600 mt-1 truncate">{{ error.error }}</p>
              </div>
              <span class="text-xs font-bold text-red-600">{{ error.count }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Execution Trend -->
      <div class="card">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Execution Trend (24h)</h2>
        
        <div v-if="executionStats.length === 0" class="text-center py-6">
          <p class="text-gray-500">No execution data</p>
        </div>

        <div v-else class="space-y-3">
          <div v-for="(stat, idx) in executionStats" :key="idx" class="border-b pb-3 last:border-b-0">
            <div class="flex justify-between items-start">
              <div class="flex-1">
                <p class="text-xs text-gray-600">{{ formatPeriod(stat.period) }}</p>
                <div class="flex gap-2 mt-1 text-xs">
                  <span class="px-2 py-1 bg-green-100 text-green-800 rounded">{{ stat.successful }}</span>
                  <span class="px-2 py-1 bg-red-100 text-red-800 rounded">{{ stat.failed }}</span>
                </div>
              </div>
              <div class="text-right">
                <p class="text-xs text-gray-500">{{ stat.avgTime }}ms</p>
                <p class="text-xs font-bold text-gray-900">{{ stat.total }} total</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Query Performance Breakdown -->
    <div class="card">
      <h2 class="text-lg font-semibold text-gray-900 mb-4">Query Performance Breakdown</h2>
      
      <div v-if="metrics.length === 0" class="text-center py-6">
        <p class="text-gray-500">No metrics data</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="table text-sm">
          <thead>
            <tr>
              <th>Endpoint</th>
              <th>Executions</th>
              <th>Avg Time</th>
              <th>Max Time</th>
              <th>Min Time</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(metric, idx) in metrics.slice(0, 20)" :key="idx">
              <td class="font-mono text-xs truncate max-w-xs">{{ metric.endpoint }}</td>
              <td class="font-semibold">{{ metric.executions }}</td>
              <td :class="metric.avgTime > 1000 ? 'text-orange-600' : 'text-green-600'">
                {{ metric.avgTime }}ms
              </td>
              <td :class="metric.maxTime > 5000 ? 'text-red-600' : 'text-orange-600'">
                {{ metric.maxTime }}ms
              </td>
              <td class="text-gray-500">{{ metric.minTime }}ms</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { performanceService } from '@/services/performance'
import type { PerformanceScore, PerformanceMetric, SlowQuery, DatabaseStats, ErrorStat, ExecutionStat, TopEndpoint } from '@/services/performance'

const performanceScore = ref<PerformanceScore>({ score: 0, rating: 'critical', maxScore: 100 })
const metrics = ref<PerformanceMetric[]>([])
const slowQueries = ref<SlowQuery[]>([])
const databaseStats = ref<DatabaseStats | null>(null)
const errorStats = ref<ErrorStat[]>([])
const executionStats = ref<ExecutionStat[]>([])
const topEndpoints = ref<TopEndpoint[]>([])
const isLoading = ref(false)

const scoreColor = computed(() => {
  if (performanceScore.value.score >= 90) return 'text-green-600'
  if (performanceScore.value.score >= 75) return 'text-blue-600'
  if (performanceScore.value.score >= 50) return 'text-yellow-600'
  if (performanceScore.value.score >= 25) return 'text-orange-600'
  return 'text-red-600'
})

const ratingColor = computed(() => {
  if (performanceScore.value.rating === 'excellent') return 'text-green-600'
  if (performanceScore.value.rating === 'good') return 'text-blue-600'
  if (performanceScore.value.rating === 'fair') return 'text-yellow-600'
  if (performanceScore.value.rating === 'poor') return 'text-orange-600'
  return 'text-red-600'
})

const metricsTotal = computed(() => {
  return metrics.value.reduce((sum, m) => sum + m.executions, 0)
})

const avgQueryTime = computed(() => {
  if (metrics.value.length === 0) return 0
  const avg = metrics.value.reduce((sum, m) => sum + m.avgTime, 0) / metrics.value.length
  return Math.round(avg)
})

const minSlowTime = computed(() => {
  if (slowQueries.value.length === 0) return 0
  return Math.min(...slowQueries.value.map(q => q.executionTime))
})

const totalErrors = computed(() => {
  return errorStats.value.reduce((sum, e) => sum + e.count, 0)
})

const mostCommonError = computed(() => {
  if (errorStats.value.length === 0) return 'None'
  const most = errorStats.value.reduce((max, e) => e.count > (max?.count ?? 0) ? e : max)
  return most?.error?.substring(0, 30) ?? 'Unknown'
})

onMounted(async () => {
  isLoading.value = true
  try {
    const dashboard = await performanceService.getDashboard()
    performanceScore.value = { score: dashboard.performanceScore, rating: 'good', maxScore: 100 }
    metrics.value = dashboard.metrics
    slowQueries.value = dashboard.slowQueries
    databaseStats.value = dashboard.databaseStats
    errorStats.value = dashboard.errorStats
    executionStats.value = dashboard.executionStats
    topEndpoints.value = dashboard.topEndpoints
  } catch (error) {
    console.error('Failed to load performance data:', error)
  } finally {
    isLoading.value = false
  }
})

const formatBytes = (bytes: number): string => {
  if (bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}

const formatTime = (timestamp: string): string => {
  return new Date(timestamp).toLocaleTimeString()
}

const formatPeriod = (period: string): string => {
  return new Date(period).toLocaleString()
}
</script>

<style scoped>
.card {
  @apply bg-white rounded-lg shadow p-6
}

.table {
  @apply w-full border-collapse
}

.table thead tr {
  @apply bg-gray-50 border-b
}

.table th {
  @apply px-4 py-2 text-left font-semibold text-gray-700 text-xs uppercase
}

.table td {
  @apply px-4 py-3 border-b border-gray-200
}

.table tbody tr:hover {
  @apply bg-gray-50
}
</style>
