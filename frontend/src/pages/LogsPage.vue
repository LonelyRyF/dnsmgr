<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { logsApi } from '@/api'
import { ClipboardList, RefreshCw, Smartphone, Globe } from 'lucide-vue-next'
import SkeletonRow from '@/components/shared/SkeletonRow.vue'

const { data, isLoading, refetch } = useQuery({
  queryKey: ['logs'],
  queryFn: async () => (await logsApi.list()).data,
})
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title flex items-center gap-2.5">
          <ClipboardList class="w-5 h-5 text-accent" /> 操作日志
        </h1>
        <p class="page-subtitle">系统用户操作记录及审计日志</p>
      </div>
      <button class="btn-ghost btn-sm" @click="refetch()"><RefreshCw class="w-3.5 h-3.5" /> 刷新</button>
    </div>
    <div class="border border-border rounded-xl overflow-hidden bg-bg-subtle">
      <table class="data-table">
        <thead>
          <tr>
            <th>用户</th>
            <th>操作分类</th>
            <th>操作详情</th>
            <th class="hidden sm:table-cell">IP 地址</th>
            <th class="hidden md:table-cell">时间</th>
          </tr>
        </thead>
        <SkeletonRow v-if="isLoading" :cols="5" :rows="10" />
        <tbody v-else>
          <tr v-if="!data?.data?.length">
            <td colspan="5" class="py-16 text-center text-text-muted text-sm">暂无操作日志</td>
          </tr>
          <tr v-for="log in data?.data" :key="log.id">
            <td class="font-medium">{{ log.username }}</td>
            <td><span class="badge badge-muted">{{ log.action }}</span></td>
            <td class="text-text-muted text-xs truncate max-w-xs" :title="log.detail">{{ log.detail }}</td>
            <td class="hidden sm:table-cell font-mono text-xs text-text-muted">
              <div class="flex items-center gap-1.5"><Globe class="w-3.5 h-3.5" /> {{ log.ip }}</div>
            </td>
            <td class="hidden md:table-cell text-text-muted text-xs whitespace-nowrap">{{ log.created_at }}</td>
          </tr>
        </tbody>
      </table>
      <div v-if="data" class="px-4 py-3 border-t border-border text-xs text-text-muted">
        共显示最近 {{ data.data?.length ?? 0 }} 条日志记录
      </div>
    </div>
  </div>
</template>
