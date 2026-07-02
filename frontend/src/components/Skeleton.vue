<template>
  <div class="space-y-4" :class="{ 'animate-pulse': isLoading }">
    <div v-for="n in count" :key="n" :style="{ height: size }">
      <div v-if="isLoading" class="bg-gray-200 rounded w-full h-full"></div>
      <slot v-else :item="n" />
    </div>
  </div>
</template>

<script setup lang="ts">
interface Props {
  isLoading: boolean
  count?: number
  size?: string
}

withDefaults(defineProps<Props>(), {
  count: 3,
  size: '20px'
})
</script>

<style scoped>
@keyframes shimmer {
  0% {
    background-position: -1000px 0;
  }
  100% {
    background-position: 1000px 0;
  }
}

.animate-pulse {
  animation: shimmer 2s infinite;
  background: linear-gradient(
    to right,
    #f0f0f0 8%,
    #f8f8f8 18%,
    #f0f0f0 33%
  );
  background-size: 800px 104px;
}
</style>
