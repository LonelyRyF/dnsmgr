<script setup lang="ts">
import { ref, computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import {
  LayoutDashboard, Globe, ShieldCheck, Truck, Activity,
  Clock, Users, Settings, ClipboardList, Lock, ChevronLeft,
  Zap
} from 'lucide-vue-next'

const route = useRoute()
const auth = useAuthStore()
const collapsed = ref(false)

defineProps<{
  open?: boolean
}>()

defineEmits<{
  toggle: []
}>()

const navItems = computed(() => [
  { name: '控制台', to: '/', icon: LayoutDashboard },
  { name: '域名管理', to: '/domains', icon: Globe },
  { name: 'DNS 账户', to: '/accounts', icon: Lock, adminOnly: true },
  { name: 'SSL 证书', to: '/certificates', icon: ShieldCheck, adminOnly: true },
  { name: '部署任务', to: '/deploy-tasks', icon: Truck, adminOnly: true },
  { name: '容灾监控', to: '/monitor', icon: Activity, adminOnly: true },
  { name: '定时任务', to: '/schedule', icon: Clock, adminOnly: true },
  { name: '用户管理', to: '/users', icon: Users, adminOnly: true },
  { name: '系统设置', to: '/settings', icon: Settings, adminOnly: true },
  { name: '操作日志', to: '/logs', icon: ClipboardList },
].filter(item => !item.adminOnly || auth.isAdmin))

function isActive(to: string) {
  if (to === '/') return route.path === '/'
  return route.path.startsWith(to)
}
</script>

<template>
  <aside
    class="hidden md:flex flex-col bg-bg-subtle border-r border-border transition-all duration-300 shrink-0 fixed md:static inset-y-0 left-0 z-40 md:z-auto"
    :class="[
      collapsed ? 'w-16' : 'w-60',
      open ? 'translate-x-0' : '-translate-x-full md:translate-x-0'
    ]"
  >
    <!-- Logo -->
    <div class="flex items-center gap-3 px-4 h-14 border-b border-border shrink-0">
      <div class="flex items-center justify-center w-8 h-8 bg-accent rounded-lg shrink-0">
        <Zap class="w-4 h-4 text-white" />
      </div>
      <span
        class="font-semibold text-text text-sm whitespace-nowrap overflow-hidden transition-all duration-300"
        :class="collapsed ? 'w-0 opacity-0' : 'w-auto opacity-100'"
      >
        DNSMgr 控制台
      </span>
    </div>

    <!-- Nav -->
    <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5">
      <template v-for="item in navItems" :key="item.to">
        <RouterLink
          :to="item.to"
          :class="isActive(item.to) ? 'nav-item-active' : 'nav-item'"
          :title="collapsed ? item.name : undefined"
        >
          <component :is="item.icon" class="w-4 h-4 shrink-0" />
          <span
            class="whitespace-nowrap overflow-hidden transition-all duration-200"
            :class="collapsed ? 'w-0 opacity-0' : 'w-auto opacity-100'"
          >
            {{ item.name }}
          </span>
        </RouterLink>
      </template>
    </nav>

    <!-- Collapse toggle -->
    <div class="px-2 pb-3 border-t border-border pt-3">
      <button
        class="btn-icon w-full flex justify-center"
        @click="collapsed = !collapsed"
        :title="collapsed ? '展开侧栏' : '收起侧栏'"
      >
        <ChevronLeft
          class="w-4 h-4 transition-transform duration-300"
          :class="collapsed ? 'rotate-180' : ''"
        />
      </button>
    </div>
  </aside>
</template>
