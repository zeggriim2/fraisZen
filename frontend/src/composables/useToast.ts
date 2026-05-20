import { ref } from 'vue'

export type ToastVariant = 'success' | 'error' | 'info'

interface Toast {
  id: number
  message: string
  variant: ToastVariant
}

const toasts = ref<Toast[]>([])
let nextId = 0

export function useToast() {
  function show(message: string, variant: ToastVariant = 'success', duration = 3500) {
    const id = ++nextId
    toasts.value.push({ id, message, variant })
    setTimeout(() => dismiss(id), duration)
  }

  function dismiss(id: number) {
    const idx = toasts.value.findIndex(t => t.id === id)
    if (idx !== -1) toasts.value.splice(idx, 1)
  }

  return { toasts, show, dismiss }
}
