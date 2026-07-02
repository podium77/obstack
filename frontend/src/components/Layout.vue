<template>
  <div class="min-h-screen bg-gray-50 flex flex-col">
    <!-- Header -->
    <header class="bg-white shadow">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
        <div class="flex items-center space-x-3">
          <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center text-white font-bold">O</div>
          <h1 class="text-2xl font-bold text-gray-900">Obstack Admin</h1>
        </div>
        <div class="flex items-center space-x-4">
          <div v-if="authStore.user" class="text-right">
            <p class="text-sm font-medium text-gray-900">{{ authStore.user.displayName }}</p>
            <p class="text-xs text-gray-500">{{ authStore.user.email }}</p>
          </div>
          <button
            @click="handleLogout"
            class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors"
          >
            Logout
          </button>
        </div>
      </div>
    </header>

    <div class="flex flex-1">
      <!-- Sidebar -->
      <aside class="w-64 bg-white shadow hidden md:block">
        <nav class="p-4 space-y-2">
          <router-link
            to="/dashboard"
            class="flex items-center space-x-3 px-4 py-2 rounded-lg transition-colors"
            :class="$route.name === 'Dashboard'
              ? 'bg-blue-100 text-blue-900'
              : 'text-gray-600 hover:bg-gray-100'"
          >
            <span>📊 Dashboard</span>
          </router-link>
          <router-link
            to="/connections"
            class="flex items-center space-x-3 px-4 py-2 rounded-lg transition-colors"
            :class="$route.name === 'Connections' || $route.name === 'ConnectionDetail'
              ? 'bg-blue-100 text-blue-900'
              : 'text-gray-600 hover:bg-gray-100'"
          >
            <span>🔌 Connections</span>
          </router-link>
          <router-link
            to="/audit"
            class="flex items-center space-x-3 px-4 py-2 rounded-lg transition-colors"
            :class="$route.name === 'AuditLogs'
              ? 'bg-blue-100 text-blue-900'
              : 'text-gray-600 hover:bg-gray-100'"
          >
            <span>📋 Audit Logs</span>
          </router-link>
          <router-link
            to="/performance"
            class="flex items-center space-x-3 px-4 py-2 rounded-lg transition-colors"
            :class="$route.name === 'Performance'
              ? 'bg-blue-100 text-blue-900'
              : 'text-gray-600 hover:bg-gray-100'"
          >
            <span>📊 Performance</span>
          </router-link>
          <router-link
            to="/data-management"
            class="flex items-center space-x-3 px-4 py-2 rounded-lg transition-colors"
            :class="$route.name === 'DataManagement'
              ? 'bg-blue-100 text-blue-900'
              : 'text-gray-600 hover:bg-gray-100'"
          >
            <span>📊 Data Management</span>
          </router-link>
          <router-link
            to="/security-settings"
            class="flex items-center space-x-3 px-4 py-2 rounded-lg transition-colors"
            :class="$route.name === 'SecuritySettings'
              ? 'bg-blue-100 text-blue-900'
              : 'text-gray-600 hover:bg-gray-100'"
          >
            <span>🔐 Security Settings</span>
          </router-link>
          <router-link
            to="/collaboration"
            class="flex items-center space-x-3 px-4 py-2 rounded-lg transition-colors"
            :class="$route.name === 'Collaboration'
              ? 'bg-blue-100 text-blue-900'
              : 'text-gray-600 hover:bg-gray-100'"
          >
            <span>👥 Collaboration</span>
          </router-link>
          <router-link
            to="/phase14"
            class="flex items-center space-x-3 px-4 py-2 rounded-lg transition-colors"
            :class="$route.name === 'Phase14'
              ? 'bg-blue-100 text-blue-900'
              : 'text-gray-600 hover:bg-gray-100'"
          >
            <span>⚡ Advanced Collab</span>
          </router-link>
          <router-link
            to="/phase15"
            class="flex items-center space-x-3 px-4 py-2 rounded-lg transition-colors"
            :class="$route.name === 'Phase15'
              ? 'bg-blue-100 text-blue-900'
              : 'text-gray-600 hover:bg-gray-100'"
          >
            <span>🔔 Notifications</span>
          </router-link>
          <router-link
            to="/phase16"
            class="flex items-center space-x-3 px-4 py-2 rounded-lg transition-colors"
            :class="$route.name === 'Phase16'
              ? 'bg-blue-100 text-blue-900'
              : 'text-gray-600 hover:bg-gray-100'"
          >
            <span>👥 Presence</span>
          </router-link>
        </nav>
      </aside>

      <!-- Main Content -->
      <main class="flex-1 overflow-auto">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

const authStore = useAuthStore()
const router = useRouter()

const handleLogout = () => {
  authStore.logout()
  router.push('/login')
}
</script>

<style scoped>
.router-link-active {
  @apply font-semibold;
}
</style>
