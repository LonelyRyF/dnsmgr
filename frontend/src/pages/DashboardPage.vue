<script setup lang="ts">
import { computed } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { RouterLink } from 'vue-router'
import { systemApi, domainsApi, certificatesApi, monitorApi } from '@/api'
import { Globe, ShieldCheck, Truck, Activity, Server, Zap, ArrowRight } from 'lucide-vue-next'
import StatusBadge from '@/components/shared/StatusBadge.vue'

const { data: sysInfo } = useQuery({
  queryKey: ['system-info'],
  queryFn: async () => (await systemApi.getInfo()).data.data,
})

const { data: domains } = useQuery({
  queryKey: ['domains', 'list'],
  queryFn: async () => (await domainsApi.list({ limit: 100 })).data,
})

const { data: certs } = useQuery({
  queryKey: ['certificates', 'list'],
  queryFn: async () => (await certificatesApi.list({ limit: 100 })).data,
})

const { data: monitorOverview } = useQuery({
  queryKey: ['monitor', 'overview'],
  queryFn: async () => (await monitorApi.overview()).data.data,
})

const statCards = computed(() => [
  {
    label: '域名总数',
    value: domains.value?.meta?.total ?? domains.value?.data?.length ?? 0,
    icon: Globe,
    color: 'accent',
    to: '/domains',
  },
  {
    label: 'SSL 证书',
    value: certs.value?.meta?.total ?? certs.value?.data?.length ?? 0,
    icon: ShieldCheck,
    color: 'success',
    to: '/certificates',
  },
  {
    label: '容灾策略',
    value: monitorOverview.value?.total ?? 0,
    icon: Activity,
    color: 'warn',
    to: '/monitor',
  },
  {
    label: '异常监控',
    value: monitorOverview.value?.unhealthy ?? 0,
    icon: Truck,
    color: 'danger',
    to: '/monitor',
  },
])

const colorMap: Record<string, string> = {
  accent: 'bg-accent/15 text-accent',
  success: 'bg-success/15 text-success',
  warn: 'bg-warn/15 text-warn',
  danger: 'bg-danger/15 text-danger',
}
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">控制台</h1>
        <p class="page-subtitle">欢迎回来，当前系统运行正常</p>
      </div>
    </div>

    <!-- Stat cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <RouterLink
        v-for="card in statCards"
        :key="card.label"
        :to="card.to"
        class="card-hover group"
      >
        <div class="flex items-center justify-between mb-3">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center" :class="colorMap[card.color]">
            <component :is="card.icon" class="w-4 h-4" />
          </div>
          <ArrowRight class="w-3.5 h-3.5 text-text-disabled group-hover:text-text-muted group-hover:translate-x-0.5 transition-all" />
        </div>
        <div class="text-2xl font-bold text-text tabular-nums">{{ card.value }}</div>
        <div class="text-xs text-text-muted mt-1">{{ card.label }}</div>
      </RouterLink>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- Monitor overview -->
      <div class="card">
        <h2 class="text-sm font-semibold text-text mb-4 flex items-center gap-2">
          <Activity class="w-4 h-4 text-warn" />
          容灾监控状态
        </h2>
        <div v-if="monitorOverview" class="space-y-2.5">
          <div class="flex justify-between text-sm">
            <span class="text-text-muted">总任务数</span>
            <span class="text-text font-medium">{{ monitorOverview.total }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-text-muted">已启用</span>
            <span class="text-text font-medium">{{ monitorOverview.active }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-text-muted">状态健康</span>
            <StatusBadge :status="1" :active-label="`${monitorOverview.healthy} 个`" />
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-text-muted">状态异常</span>
            <StatusBadge :status="monitorOverview.unhealthy > 0 ? 2 : 1"
              :active-label="`0 个`"
              :inactive-label="''"
              :labels="{ 2: `${monitorOverview.unhealthy} 个` }" />
          </div>
        </div>
        <div v-else class="space-y-2.5">
          <div v-for="i in 4" :key="i" class="skeleton h-5 rounded-md" />
        </div>
      </div>

      <!-- Server info -->
      <div class="card">
        <h2 class="text-sm font-semibold text-text mb-4 flex items-center gap-2">
          <Server class="w-4 h-4 text-accent" />
          服务器信息
        </h2>
        <div v-if="sysInfo" class="space-y-2.5">
          <div class="flex justify-between text-sm">
            <span class="text-text-muted">框架版本</span>
            <span class="text-text font-mono text-xs bg-bg-hover px-2 py-0.5 rounded">{{ sysInfo.framework_version }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-text-muted">PHP 版本</span>
            <span class="text-text font-mono text-xs bg-bg-hover px-2 py-0.5 rounded">{{ sysInfo.php_version }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-text-muted">数据库</span>
            <span class="text-text font-mono text-xs bg-bg-hover px-2 py-0.5 rounded">{{ sysInfo.mysql_version }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-text-muted">Web 服务器</span>
            <span class="text-text text-xs">{{ sysInfo.software }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-text-muted">服务器时间</span>
            <span class="text-text text-xs">{{ sysInfo.date }}</span>
          </div>
        </div>
        <div v-else class="space-y-2.5">
          <div v-for="i in 5" :key="i" class="skeleton h-5 rounded-md" />
        </div>
      </div>
    </div>
  </div>
</template>
