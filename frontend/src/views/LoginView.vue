<template>
  <div class="min-h-screen bg-gradient-to-br from-blue-600 to-blue-900 flex items-center justify-center">
    <div class="w-full max-w-md">
      <!-- Logo & Title -->
      <div class="text-center mb-8">
        <div class="w-16 h-16 bg-white rounded-lg flex items-center justify-center mx-auto mb-4 text-2xl font-bold text-blue-600">O</div>
        <h1 class="text-3xl font-bold text-white">Obstack Admin</h1>
        <p class="text-blue-100 mt-2">Database Management Console</p>
      </div>

      <!-- Login Card -->
      <div class="bg-white rounded-lg shadow-xl p-8">
        <!-- Error Alert -->
        <div v-if="authStore.error" class="alert-error mb-4">
          {{ authStore.error }}
        </div>

        <!-- Login Form -->
        <form @submit.prevent="handleLogin" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Username or Email
            </label>
            <input
              v-model="form.username"
              type="text"
              class="input"
              placeholder="admin"
              required
              :disabled="authStore.isLoading"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Password
            </label>
            <input
              v-model="form.password"
              type="password"
              class="input"
              placeholder="••••••••"
              required
              :disabled="authStore.isLoading"
            />
          </div>

          <button
            type="submit"
            :disabled="authStore.isLoading"
            class="btn-primary w-full"
          >
            <span v-if="authStore.isLoading" class="inline-block mr-2 animate-spin">⟳</span>
            {{ authStore.isLoading ? 'Logging in...' : 'Login' }}
          </button>
        </form>

        <!-- Demo Credentials -->
        <div class="mt-6 p-4 bg-blue-50 rounded-lg text-sm text-blue-900 border border-blue-200">
          <p class="font-semibold mb-2">Demo Credentials:</p>
          <p>Email: <code class="bg-white px-2 py-1 rounded">admin@obstack.local</code></p>
          <p>Password: <code class="bg-white px-2 py-1 rounded">TestPassword123</code></p>
        </div>
      </div>

      <!-- Footer -->
      <p class="text-center text-blue-100 text-sm mt-6">
        © 2025 Obstack. All rights reserved.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { reactive } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

const authStore = useAuthStore()
const router = useRouter()

const form = reactive({
  username: '',
  password: ''
})

const handleLogin = async () => {
  try {
    await authStore.login({
      username: form.username,
      password: form.password
    })
    router.push('/dashboard')
  } catch (error) {
    // Erreur déjà définie dans le store
  }
}
</script>

<style scoped>
code {
  @apply font-mono text-xs;
}
</style>
