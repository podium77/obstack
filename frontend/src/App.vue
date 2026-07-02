<template>
  <div id="app" class="min-h-screen bg-gray-50">
    <router-view />
    <Toast />
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import Toast from '@/components/Toast.vue'

const authStore = useAuthStore()
const router = useRouter()

onMounted(async () => {
  // Vérifier si l'utilisateur est authentifié au chargement
  if (authStore.token) {
    authStore.validateToken()
  } else if (router.currentRoute.value.path !== '/login') {
    // Rediriger vers login si pas authentifié
    router.push('/login')
  }
})
</script>

<style scoped>
</style>
