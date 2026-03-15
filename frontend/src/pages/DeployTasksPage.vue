<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { deployApi } from '@/api'
import { Truck, RefreshCw, Clock } from 'lucide-vue-next'
import StatusBadge from '@/components/shared/StatusBadge.vue'
import SkeletonRow from '@/components/shared/SkeletonRow.vue'

const { data, isLoading, refetch } = useQuery({
  queryKey: ['deploy-tasks'],
  queryFn: async () => (await deployApi.taskList()).data,
})

const statusLabels: Record<number, string> = {
  0: '待处理',
  1: '部署成功',
  '-1': '部署失败',
}
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title flex items-center gap-2.5">
          <Truck class="w-5 h-5 text-accent" /> 部署任务
        </h1>
        <p class="page-subtitle">证书自动分发与服务器部署任务</p>
      </div>
      <button class="btn-ghost btn-sm" @click="refetch()"><RefreshCw class="w-3.5 h-3.5" /> 刷新</button>
    </div>
    <div class="border border-border rounded-xl overflow-hidden bg-bg-subtle">
      <table class="data-table">
        <thead>
          <tr>
            <th>任务名称</th>
            <th>关联证书 (ID)</th>
            <th>启用状态</th>
            <th>执行状态</th>
            <th class="hidden md:table-cell">最后执行时间</th>
          </tr>
        </thead>
        <SkeletonRow v-if="isLoading" :cols="5" :rows="6" />
        <tbody v-else>
          <tr v-if="!data?.data?.length">
            <td colspan="5" class="py-16 text-center text-text-muted text-sm">暂无部署任务</td>
          </tr>
          <tr v-for="task in data?.data" :key="task.id">
            <td class="font-medium">{{ task.name }}</td>
            <td class="font-mono text-xs text-text-muted">#{{ task.cert_id }}</td>
            <td><StatusBadge :status="task.active" active-label="已启用" inactive-label="已停用" /></td>
            <td>
              <span class="badge" :class="task.status === 1 ? 'badge-success' : task.status === -1 ? 'badge-danger' : 'badge-info'">
                <span class="inline-block w-1.5 h-1.5 rounded-full bg-current" />
                {{ statusLabels[task.status] ?? task.status }}
              </span>
            </td>
            <td class="hidden md:table-cell text-text-muted text-xs">
              <span v-if="task.last_run" class="flex items-center gap-1.5"><Clock class="w-3.5 h-3.5" /> {{ task.last_run }}</span>
              <span v-else>—</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
