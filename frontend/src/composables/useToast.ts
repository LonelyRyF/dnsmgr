import { ref } from 'vue'

export interface Toast {
  id: string
  type: 'success' | 'error' | 'warn' | 'info'
  title?: string
  message: string
  duration?: number
}

const toasts = ref<Toast[]>([])

export function useToast() {
  function add(toast: Omit<Toast, 'id'>) {
    const id = Math.random().toString(36).slice(2)
    const duration = toast.duration ?? 4000
    toasts.value.push({ id, ...toast })
    if (duration > 0) {
      setTimeout(() => remove(id), duration)
    }
    return id
  }

  const success = (message: string, title?: string) =>
    add({ type: 'success', message, title })

  const error = (message: string, title?: string) =>
    add({ type: 'error', message, title, duration: 6000 })

  const warn = (message: string, title?: string) =>
    add({ type: 'warn', message, title })

  const info = (message: string, title?: string) =>
    add({ type: 'info', message, title })

  function remove(id: string) {
    const idx = toasts.value.findIndex(t => t.id === id)
    if (idx > -1) toasts.value.splice(idx, 1)
  }

  return { toasts, add, remove, success, error, warn, info }
}
