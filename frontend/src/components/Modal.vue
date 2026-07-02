<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="isOpen" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <Transition name="modal-slide">
          <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full">
            <!-- Header -->
            <div class="flex justify-between items-center p-6 border-b border-gray-200">
              <h2 class="text-xl font-bold text-gray-900">{{ title }}</h2>
              <button
                @click="close"
                class="text-gray-500 hover:text-gray-700 text-2xl leading-none"
              >
                ×
              </button>
            </div>

            <!-- Body -->
            <div class="p-6 max-h-96 overflow-y-auto">
              <slot />
            </div>

            <!-- Footer -->
            <div v-if="showFooter" class="flex justify-end space-x-2 p-6 border-t border-gray-200 bg-gray-50">
              <button
                v-if="showCancel"
                @click="close"
                class="btn btn-secondary"
              >
                {{ cancelText }}
              </button>
              <button
                v-if="showConfirm"
                @click="confirm"
                :disabled="confirmDisabled"
                class="btn btn-primary"
              >
                {{ confirmText }}
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
interface Props {
  isOpen: boolean
  title: string
  showFooter?: boolean
  showCancel?: boolean
  showConfirm?: boolean
  cancelText?: string
  confirmText?: string
  confirmDisabled?: boolean
}

interface Emits {
  (e: 'close'): void
  (e: 'confirm'): void
}

withDefaults(defineProps<Props>(), {
  showFooter: true,
  showCancel: true,
  showConfirm: true,
  cancelText: 'Cancel',
  confirmText: 'Confirm',
  confirmDisabled: false
})

const emit = defineEmits<Emits>()

const close = () => emit('close')
const confirm = () => emit('confirm')
</script>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.3s ease;
}

.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}

.modal-slide-enter-active,
.modal-slide-leave-active {
  transition: transform 0.3s ease, opacity 0.3s ease;
}

.modal-slide-enter-from,
.modal-slide-leave-to {
  transform: scale(0.95);
  opacity: 0;
}
</style>
