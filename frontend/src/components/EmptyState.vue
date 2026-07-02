<template>
  <div class="flex flex-col items-center justify-center py-12 px-4">
    <div class="text-6xl mb-4">{{ icon }}</div>
    <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ title }}</h3>
    <p class="text-gray-600 text-center max-w-md mb-4">{{ description }}</p>
    <slot />
  </div>
</template>

<script setup lang="ts">
interface Props {
  title: string
  description?: string
  icon?: string
}

withDefaults(defineProps<Props>(), {
  description: '',
  icon: '📭'
})
</script>
