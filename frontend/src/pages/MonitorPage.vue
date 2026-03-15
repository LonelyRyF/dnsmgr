<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { monitorApi, type MonitorTask } from '@/api'
import { Activity, RefreshCw } from 'lucide-vue-next'
import StatusBadge from '@/components/shared/StatusBadge.vue'
import SkeletonRow from '@/components/shared/SkeletonRow.vue'

const { data: overview, isLoading: overviewLoading } = useQuery({
  queryKey: ['monitor-overview'],
  queryFn: async () => (await monitorApi.overview()).data.data,
  refetchInterval: 30_000,
})
const { data, isLoading, refetch } = useQuery({
  queryKey: ['monitor-tasks'],
  queryFn: async () => (await monitorApi.taskList()).data,
})
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title flex items-center gap-2.5">
          <Activity class="w-5 h-5 text-warn" /> 容灾监控
        </h1>
        <p class="page-subtitle">实时监控 DNS 切换策略状态</p>
      </div>
      <button class="btn-ghost btn-sm" @click="refetch()"><RefreshCw class="w-3.5 h-3.5" /> 刷新</button>
    </div>

    <!-- Overview cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
      <div class="card text-center">
        <div class="text-2xl font-bold text-text">{{ overview?.total ?? '—' }}</div>
        <div class="text-xs text-text-muted mt-1">总策略数</div>
      </div>
      <div class="card text-center">
        <div class="text-2xl font-bold text-accent">{{ overview?.active ?? '—' }}</div>
        <div class="text-xs text-text-muted mt-1">已启用</div>
      </div>
      <div class="card text-center">
        <div class="text-2xl font-bold text-success">{{ overview?.healthy ?? '—' }}</div>
        <div class="text-xs text-text-muted mt-1">状态健康</div>
      </div>
      <div class="card text-center">
        <div class="text-2xl font-bold text-danger">{{ overview?.unhealthy ?? '—' }}</div>
        <div class="text-xs text-text-muted mt-1">状态异常</div>
      </div>
    </div>

    <div class="border border-border rounded-xl overflow-hidden">
      <table class="data-table">
        <thead>
          <tr>
            <th>任务名称</th>
            <th>监控地址</th>
            <th>健康状态</th>
            <th>是否启用</th>
            <th class="hidden md:table-cell">最后检查</th>
          </tr>
        </thead>
        <SkeletonRow v-if="isLoading" :cols="5" :rows="5" />
        <tbody v-else>
          <tr v-if="!data?.data?.length">
            <td colspan="5" class="py-16 text-center text-text-muted text-sm">暂无监控任务</td>
          </tr>
          <tr v-for="task in data?.data" :key="task.id">
            <td class="font-medium">{{ task.name }}</td>
            <td class="font-mono text-xs text-text-muted">{{ task.host }}</td>
            <td><StatusBadge :status="task.status" active-label="健康" inactive-label="异常" /></td>
            <td><StatusBadge :status="task.active" active-label="已启用" inactive-label="已停用" /></td>
            <td class="hidden md:table-cell text-text-muted text-xs">{{ task.last_check ?? '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
