<script setup lang="ts">
import { useToast } from '@/composables/useToast'
import { CheckCircle2, XCircle, AlertTriangle, Info, X } from 'lucide-vue-next'

const { toasts, remove } = useToast()

const icons = {
  success: CheckCircle2,
  error: XCircle,
  warn: AlertTriangle,
  info: Info,
}

const styles = {
  success: 'border-success/30 bg-success/10 text-success',
  error: 'border-danger/30 bg-danger/10 text-danger',
  warn: 'border-warn/30 bg-warn/10 text-warn',
  info: 'border-info/30 bg-info/10 text-info',
}
</script>

<template>
  <Teleport to="body">
    <div class="fixed bottom-5 right-5 z-[9999] flex flex-col gap-2.5 max-w-sm w-full pointer-events-none">
      <TransitionGroup name="toast" tag="div" class="flex flex-col gap-2.5">
        <div
          v-for="toast in toasts"
          :key="toast.id"
          class="pointer-events-auto flex items-start gap-3 px-4 py-3.5 rounded-xl border
                 bg-bg-subtle shadow-panel backdrop-blur-sm animate-fade-in"
          :class="styles[toast.type]"
        >
          <component :is="icons[toast.type]" class="w-4 h-4 shrink-0 mt-0.5" />
          <div class="flex-1 min-w-0">
            <p v-if="toast.title" class="text-xs font-semibold mb-0.5">{{ toast.title }}</p>
            <p class="text-xs opacity-90">{{ toast.message }}</p>
          </div>
          <button
            class="shrink-0 opacity-60 hover:opacity-100 transition-opacity p-0.5"
            @click="remove(toast.id)"
          >
            <X class="w-3.5 h-3.5" />
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
.toast-enter-active, .toast-leave-active {
  transition: all 0.3s ease;
}
.toast-enter-from {
  opacity: 0;
  transform: translateX(100%) scale(0.95);
}
.toast-leave-to {
  opacity: 0;
  transform: translateX(100%) scale(0.95);
  max-height: 0;
  margin: 0;
  padding: 0;
}
.toast-move {
  transition: all 0.3s ease;
}
</style>
