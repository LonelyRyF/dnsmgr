<script setup lang="ts">
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { LogOut, User, ChevronRight, Menu } from 'lucide-vue-next'
import { ref, computed } from 'vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const showUserMenu = ref(false)

defineProps<{
  sidebarOpen?: boolean
}>()

defineEmits<{
  toggleSidebar: []
}>()

const breadcrumbs = computed(() => {
  const parts = route.matched
    .filter(r => r.meta?.title)
    .map(r => ({ title: r.meta.title as string }))
  return parts
})

async function logout() {
  auth.logout()
  router.push('/login')
}
</script>

<template>
  <header class="h-14 border-b border-border bg-bg-subtle shrink-0 flex items-center px-6 gap-4">
    <!-- Mobile menu button -->
    <button
      class="md:hidden flex items-center justify-center w-8 h-8 rounded-lg hover:bg-bg-hover transition-colors"
      @click="$emit('toggleSidebar')"
      title="切换菜单"
    >
      <Menu class="w-4 h-4 text-text" />
    </button>

    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-1.5 text-sm flex-1 min-w-0">
      <span class="text-text-disabled">首页</span>
      <template v-for="(crumb, i) in breadcrumbs" :key="i">
        <ChevronRight class="w-3.5 h-3.5 text-text-disabled shrink-0" />
        <span :class="i === breadcrumbs.length - 1 ? 'text-text font-medium' : 'text-text-muted'">
          {{ crumb.title }}
        </span>
      </template>
    </nav>

    <!-- User menu -->
    <div class="relative shrink-0">
      <button
        class="flex items-center gap-2 px-3 py-1.5 rounded-lg hover:bg-bg-hover transition-colors"
        @click="showUserMenu = !showUserMenu"
      >
        <div class="w-7 h-7 rounded-full bg-accent/20 flex items-center justify-center">
          <User class="w-3.5 h-3.5 text-accent" />
        </div>
        <span class="text-sm text-text font-medium">{{ auth.user?.username }}</span>
      </button>

      <!-- Dropdown -->
      <Transition name="fade-dropdown">
        <div
          v-if="showUserMenu"
          class="absolute right-0 top-full mt-1 w-44 bg-bg-subtle border border-border rounded-xl shadow-panel z-50 overflow-hidden py-1"
          @click.stop
        >
          <div class="px-4 py-2.5 border-b border-border">
            <p class="text-xs font-medium text-text">{{ auth.user?.username }}</p>
            <p class="text-xs text-text-muted mt-0.5">
              {{ auth.isAdmin ? '管理员' : '普通用户' }}
            </p>
          </div>
          <button
            class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-danger hover:bg-danger/10 transition-colors"
            @click="logout"
          >
            <LogOut class="w-3.5 h-3.5" />
            退出登录
          </button>
        </div>
      </Transition>
    </div>

    <!-- Close overlay -->
    <div
      v-if="showUserMenu"
      class="fixed inset-0 z-40"
      @click="showUserMenu = false"
    />
  </header>
</template>

<style scoped>
.fade-dropdown-enter-active,
.fade-dropdown-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.fade-dropdown-enter-from,
.fade-dropdown-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
