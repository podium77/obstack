<template>
  <Teleport to="body">
    <Transition name="toast-list">
      <div v-if="toasts.length > 0" class="fixed top-4 right-4 z-40 space-y-2">
        <Transition
          v-for="toast in toasts"
          :key="toast.id"
          name="toast-slide"
        >
          <div
            :class="{
              'bg-green-50 border-green-200 text-green-800': toast.type === 'success',
              'bg-red-50 border-red-200 text-red-800': toast.type === 'error',
              'bg-blue-50 border-blue-200 text-blue-800': toast.type === 'info',
              'bg-yellow-50 border-yellow-200 text-yellow-800': toast.type === 'warning'
            }"
            class="border rounded-lg shadow-lg p-4 max-w-sm w-full"
          >
            <div class="flex items-start space-x-3">
              <span class="text-xl flex-shrink-0">
                {{ toastIcon(toast.type) }}
              </span>
              <div class="flex-1">
                <p v-if="toast.title" class="font-semibold">{{ toast.title }}</p>
                <p v-if="toast.message" class="text-sm">{{ toast.message }}</p>
              </div>
              <button
                @click="removeToast(toast.id)"
                class="text-current opacity-50 hover:opacity-75"
              >
                ×
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'

interface Toast {
  id: number
  type: 'success' | 'error' | 'info' | 'warning'
  title?: string
  message: string
  duration?: number
}

const toasts = ref<Toast[]>([])
let nextId = 0

onMounted(() => {
  // Make toast service available globally
  window.__toastService = {
    show: (message: string, type: 'success' | 'error' | 'info' | 'warning' = 'info', duration = 3000) => {
      addToast({ type, message, duration })
    },
    success: (message: string, duration?: number) => addToast({ type: 'success', message, duration }),
    error: (message: string, duration?: number) => addToast({ type: 'error', message, duration }),
    info: (message: string, duration?: number) => addToast({ type: 'info', message, duration }),
    warning: (message: string, duration?: number) => addToast({ type: 'warning', message, duration })
  }
})

const addToast = (toast: Omit<Toast, 'id'>) => {
  const id = nextId++
  const newToast: Toast = { ...toast, id }
  toasts.value.push(newToast)

  if (toast.duration !== 0) {
    setTimeout(() => removeToast(id), toast.duration || 3000)
  }
}

const removeToast = (id: number) => {
  const index = toasts.value.findIndex(t => t.id === id)
  if (index > -1) {
    toasts.value.splice(index, 1)
  }
}

const toastIcon = (type: string) => {
  switch (type) {
    case 'success':
      return '✅'
    case 'error':
      return '❌'
    case 'warning':
      return '⚠️'
    case 'info':
    default:
      return 'ℹ️'
  }
}
</script>

<style scoped>
.toast-slide-enter-active,
.toast-slide-leave-active {
  transition: transform 0.3s ease, opacity 0.3s ease;
}

.toast-slide-enter-from,
.toast-slide-leave-to {
  transform: translateX(400px);
  opacity: 0;
}

.toast-list-enter-active,
.toast-list-leave-active {
  transition: opacity 0.3s ease;
}
</style>
