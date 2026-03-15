<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next'

interface Props {
  open: boolean
  title?: string
  message?: string
  confirmLabel?: string
  cancelLabel?: string
  danger?: boolean
  loading?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  title: '确认操作',
  message: '确定要执行此操作吗？',
  confirmLabel: '确认',
  cancelLabel: '取消',
  danger: true,
})

const emit = defineEmits<{
  'update:open': [value: boolean]
  confirm: []
  cancel: []
}>()

function cancel() {
  emit('update:open', false)
  emit('cancel')
}

function confirm() {
  emit('confirm')
}
</script>

<template>
  <Teleport to="body">
    <Transition name="dialog">
      <div
        v-if="open"
        class="fixed inset-0 z-[60] flex items-center justify-center p-4"
      >
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="cancel" />
        <div class="relative w-full max-w-sm bg-bg-subtle border border-border rounded-2xl shadow-panel p-6 animate-fade-in">
          <!-- Icon -->
          <div
            class="flex items-center justify-center w-11 h-11 rounded-full mb-4 mx-auto"
            :class="danger ? 'bg-danger/15' : 'bg-accent/15'"
          >
            <AlertTriangle
              class="w-5 h-5"
              :class="danger ? 'text-danger' : 'text-accent'"
            />
          </div>

          <h3 class="text-base font-semibold text-text text-center mb-2">{{ title }}</h3>
          <p class="text-sm text-text-muted text-center mb-6">{{ message }}</p>

          <div class="flex gap-3">
            <button class="btn-outline flex-1" @click="cancel">{{ cancelLabel }}</button>
            <button
              class="flex-1 btn"
              :class="danger ? 'btn-danger' : 'btn-primary'"
              :disabled="loading"
              @click="confirm"
            >
              <span v-if="loading" class="w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin" />
              {{ confirmLabel }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.dialog-enter-active, .dialog-leave-active {
  transition: opacity 0.2s ease;
}
.dialog-enter-from, .dialog-leave-to {
  opacity: 0;
}
</style>
