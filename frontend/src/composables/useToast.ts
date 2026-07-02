/**
 * Composable for toast notifications
 * Usage: const toast = useToast()
 *        toast.success('Operation successful!')
 *        toast.error('Something went wrong')
 *        toast.info('Please note...')
 *        toast.warning('Be careful!')
 */
export function useToast() {
  const getToastService = () => {
    // @ts-ignore - injected by Toast component
    return window.__toastService || {
      success: console.log,
      error: console.error,
      info: console.log,
      warning: console.warn,
      show: console.log
    }
  }

  return {
    success: (message: string, duration?: number) => {
      getToastService().success(message, duration)
    },
    error: (message: string, duration?: number) => {
      getToastService().error(message, duration)
    },
    info: (message: string, duration?: number) => {
      getToastService().info(message, duration)
    },
    warning: (message: string, duration?: number) => {
      getToastService().warning(message, duration)
    },
    show: (message: string, type: string = 'info', duration?: number) => {
      getToastService().show(message, type, duration)
    }
  }
}
