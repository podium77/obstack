import { ref } from 'vue'

interface ConfirmOptions {
  title: string
  message: string
  confirmText?: string
  cancelText?: string
}

/**
 * Composable for confirmation dialogs
 * Usage: const confirm = useConfirm()
 *        const result = await confirm.ask({ title: 'Delete?', message: 'Are you sure?' })
 */
export function useConfirm() {
  const isOpen = ref(false)
  const options = ref<ConfirmOptions>({
    title: '',
    message: '',
    confirmText: 'Confirm',
    cancelText: 'Cancel'
  })
  let resolveConfirm: ((value: boolean) => void) | null = null

  const ask = (opts: ConfirmOptions): Promise<boolean> => {
    return new Promise((resolve) => {
      options.value = {
        confirmText: 'Confirm',
        cancelText: 'Cancel',
        ...opts
      }
      isOpen.value = true
      resolveConfirm = resolve
    })
  }

  const confirm = () => {
    isOpen.value = false
    if (resolveConfirm) {
      resolveConfirm(true)
      resolveConfirm = null
    }
  }

  const cancel = () => {
    isOpen.value = false
    if (resolveConfirm) {
      resolveConfirm(false)
      resolveConfirm = null
    }
  }

  return {
    isOpen,
    options,
    ask,
    confirm,
    cancel
  }
}
