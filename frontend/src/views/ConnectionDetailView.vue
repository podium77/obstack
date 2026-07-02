<template>
  <div class="p-8">
    <div class="card">
      <h1 class="text-3xl font-bold text-gray-900 mb-4">Connection Details</h1>
      <p class="text-gray-600">
        This view will display detailed information about a selected database connection.
      </p>
      <router-link to="/connections" class="btn btn-primary mt-6">
        Back to Connections
      </router-link>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useDatabaseStore } from '@/stores/database'

const route = useRoute()
const databaseStore = useDatabaseStore()

onMounted(async () => {
  const id = parseInt(route.params.id as string)
  if (id) {
    await databaseStore.selectConnection(id)
  }
})
</script>
