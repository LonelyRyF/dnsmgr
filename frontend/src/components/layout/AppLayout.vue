<script setup lang="ts">
import AppSidebar from './AppSidebar.vue'
import AppTopbar from './AppTopbar.vue'
import ToastNotification from '@/components/shared/ToastNotification.vue'
</script>

<template>
  <div class="flex h-screen overflow-hidden bg-bg">
    <AppSidebar />
    <div class="flex flex-col flex-1 min-w-0 overflow-hidden">
      <AppTopbar />
      <main class="flex-1 overflow-y-auto px-6 py-6">
        <RouterView />
      </main>
    </div>
    <ToastNotification />
  </div>
</template>
