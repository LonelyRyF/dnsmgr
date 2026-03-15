<script setup lang="ts">
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { usersApi, type UserItem } from '@/api'
import { useToast } from '@/composables/useToast'
import { ref } from 'vue'
import { Users, RefreshCw, Trash2, ToggleLeft, ToggleRight } from 'lucide-vue-next'
import StatusBadge from '@/components/shared/StatusBadge.vue'
import SkeletonRow from '@/components/shared/SkeletonRow.vue'
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue'

const toast = useToast()
const qc = useQueryClient()
const deleteTarget = ref<UserItem | null>(null)

const { data, isLoading, refetch } = useQuery({
  queryKey: ['users'],
  queryFn: async () => (await usersApi.list()).data,
})

const deleteMutation = useMutation({
  mutationFn: (id: number) => usersApi.delete(id),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['users'] })
    deleteTarget.value = null
    toast.success('用户已删除')
  },
  onError: () => toast.error('删除失败'),
})

const toggleMutation = useMutation({
  mutationFn: (user: UserItem) => usersApi.toggleStatus(user.id),
  onMutate: async (user) => {
    const prev = qc.getQueryData(['users'])
    qc.setQueryData(['users'], (old: typeof data.value) => ({
      ...old, data: old?.data?.map(u => u.id === user.id ? { ...u, status: u.status === 1 ? 0 : 1 } : u),
    }))
    return { prev }
  },
  onError: (_e, _v, ctx) => {
    if (ctx?.prev) qc.setQueryData(['users'], ctx.prev)
    toast.error('状态切换失败')
  },
  onSuccess: () => toast.success('用户状态已更新'),
})
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title flex items-center gap-2.5">
          <Users class="w-5 h-5 text-accent" /> 用户管理
        </h1>
        <p class="page-subtitle">管理系统用户账号</p>
      </div>
      <button class="btn-ghost btn-sm" @click="refetch()"><RefreshCw class="w-3.5 h-3.5" /> 刷新</button>
    </div>
    <div class="border border-border rounded-xl overflow-hidden">
      <table class="data-table">
        <thead>
          <tr>
            <th>用户名</th>
            <th class="hidden sm:table-cell">权限级别</th>
            <th>状态</th>
            <th class="hidden md:table-cell">注册时间</th>
            <th class="text-right">操作</th>
          </tr>
        </thead>
        <SkeletonRow v-if="isLoading" :cols="5" :rows="5" />
        <tbody v-else>
          <tr v-if="!data?.data?.length">
            <td colspan="5" class="py-16 text-center text-text-muted text-sm">暂无用户</td>
          </tr>
          <tr v-for="user in data?.data" :key="user.id" class="group">
            <td class="font-medium">{{ user.username }}</td>
            <td class="hidden sm:table-cell">
              <span :class="user.level === 2 ? 'badge-success badge' : 'badge-muted badge'">
                {{ user.level === 2 ? '管理员' : '普通用户' }}
              </span>
            </td>
            <td>
              <button @click="toggleMutation.mutate(user)">
                <StatusBadge :status="user.status" />
              </button>
            </td>
            <td class="hidden md:table-cell text-text-muted text-xs">{{ user.regtime }}</td>
            <td class="text-right">
              <div class="flex justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <button class="btn-icon" @click="toggleMutation.mutate(user)" title="切换状态">
                  <ToggleLeft v-if="user.status === 0" class="w-3.5 h-3.5 text-text-disabled" />
                  <ToggleRight v-else class="w-3.5 h-3.5 text-success" />
                </button>
                <button class="btn-icon hover:text-danger" @click="deleteTarget = user"><Trash2 class="w-3.5 h-3.5" /></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <ConfirmDialog
      :open="!!deleteTarget"
      title="删除用户"
      :message="`确定删除用户 ${deleteTarget?.username}？`"
      :loading="deleteMutation.isPending.value"
      @update:open="(v) => { if (!v) deleteTarget = null }"
      @confirm="deleteTarget && deleteMutation.mutate(deleteTarget.id)"
    />
  </div>
</template>
