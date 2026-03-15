<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { scheduleApi } from '@/api'
import { Clock, RefreshCw } from 'lucide-vue-next'
import StatusBadge from '@/components/shared/StatusBadge.vue'
import SkeletonRow from '@/components/shared/SkeletonRow.vue'

const { data, isLoading, refetch } = useQuery({
  queryKey: ['schedule-tasks'],
  queryFn: async () => (await scheduleApi.list()).data,
})
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title flex items-center gap-2.5">
          <Clock class="w-5 h-5 text-accent" /> 定时任务
        </h1>
        <p class="page-subtitle">管理周期性执行计划任务</p>
      </div>
      <button class="btn-ghost btn-sm" @click="refetch()"><RefreshCw class="w-3.5 h-3.5" /> 刷新</button>
    </div>
    <div class="border border-border rounded-xl overflow-hidden bg-bg-subtle">
      <table class="data-table">
        <thead>
          <tr>
            <th>任务名称</th>
            <th>触发动作</th>
            <th>Cron 表达式</th>
            <th>启用状态</th>
          </tr>
        </thead>
        <SkeletonRow v-if="isLoading" :cols="4" :rows="5" />
        <tbody v-else>
          <tr v-if="!data?.data?.length">
            <td colspan="4" class="py-16 text-center text-text-muted text-sm">暂无定时任务</td>
          </tr>
          <tr v-for="task in data?.data" :key="task.id">
            <td class="font-medium">{{ task.name }}</td>
            <td class="font-mono text-xs text-text-muted">{{ task.action }}</td>
            <td class="font-mono text-xs bg-bg-hover px-2 py-0.5 rounded max-w-max">{{ task.cron }}</td>
            <td><StatusBadge :status="task.active" active-label="已启用" inactive-label="已停用" /></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
