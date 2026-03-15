<script setup lang="ts">
import { X } from 'lucide-vue-next'

interface Props {
  open: boolean
  title?: string
  width?: string
}

const props = withDefaults(defineProps<Props>(), {
  width: 'max-w-lg',
})

const emit = defineEmits<{
  'update:open': [value: boolean]
  close: []
}>()

function close() {
  emit('update:open', false)
  emit('close')
}
</script>

<template>
  <Teleport to="body">
    <Transition name="overlay">
      <div
        v-if="open"
        class="fixed inset-0 z-50 flex justify-end"
        @click.self="close"
      >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="close" />

        <!-- Panel -->
        <Transition name="slide-panel" appear>
          <div
            v-if="open"
            class="relative flex flex-col bg-bg-subtle border-l border-border shadow-panel h-full w-full overflow-hidden"
            :class="props.width"
          >
            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-border shrink-0">
              <h3 class="text-base font-semibold text-text">{{ title }}</h3>
              <button class="btn-icon" @click="close">
                <X class="w-4 h-4" />
              </button>
            </div>

            <!-- Body (scrollable) -->
            <div class="flex-1 overflow-y-auto px-6 py-5">
              <slot />
            </div>

            <!-- Footer slot -->
            <div v-if="$slots.footer" class="border-t border-border px-6 py-4 shrink-0">
              <slot name="footer" />
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.overlay-enter-active, .overlay-leave-active {
  transition: opacity 0.25s ease;
}
.overlay-enter-from, .overlay-leave-to {
  opacity: 0;
}
.slide-panel-enter-active, .slide-panel-leave-active {
  transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1);
}
.slide-panel-enter-from, .slide-panel-leave-to {
  transform: translateX(100%);
}
</style>
