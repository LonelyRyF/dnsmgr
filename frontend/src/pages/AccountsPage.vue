<script setup lang="ts">
import { ref } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { accountsApi, type DnsAccount } from '@/api'
import { useToast } from '@/composables/useToast'
import { Lock, Plus, Pencil, Trash2, RefreshCw } from 'lucide-vue-next'
import StatusBadge from '@/components/shared/StatusBadge.vue'
import SkeletonRow from '@/components/shared/SkeletonRow.vue'
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue'
import SlideOver from '@/components/shared/SlideOver.vue'

const toast = useToast()
const qc = useQueryClient()
const showAddSlide = ref(false)
const deleteTarget = ref<DnsAccount | null>(null)
const addForm = ref({ name: '', type: '', config: '{}' })

const { data, isLoading, refetch } = useQuery({
  queryKey: ['accounts'],
  queryFn: async () => (await accountsApi.list()).data,
})

const addMutation = useMutation({
  mutationFn: () => accountsApi.create({ ...addForm.value, config: JSON.parse(addForm.value.config || '{}') }),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['accounts'] })
    showAddSlide.value = false
    addForm.value = { name: '', type: '', config: '{}' }
    toast.success('账户已添加')
  },
  onError: () => toast.error('添加失败'),
})

const deleteMutation = useMutation({
  mutationFn: (id: number) => accountsApi.delete(id),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['accounts'] })
    deleteTarget.value = null
    toast.success('账户已删除')
  },
  onError: () => toast.error('删除失败'),
})
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title flex items-center gap-2.5">
          <Lock class="w-5 h-5 text-accent" /> DNS 账户管理
        </h1>
        <p class="page-subtitle">管理各 DNS 服务商的 API 凭证</p>
      </div>
      <div class="flex gap-2">
        <button class="btn-ghost btn-sm" @click="refetch()"><RefreshCw class="w-3.5 h-3.5" /> 刷新</button>
        <button class="btn-primary btn-sm" @click="showAddSlide = true"><Plus class="w-3.5 h-3.5" /> 添加账户</button>
      </div>
    </div>
    <div class="border border-border rounded-xl overflow-hidden">
      <table class="data-table">
        <thead>
          <tr>
            <th>账户名称</th>
            <th>服务商类型</th>
            <th class="hidden md:table-cell">域名数</th>
            <th>状态</th>
            <th class="text-right">操作</th>
          </tr>
        </thead>
        <SkeletonRow v-if="isLoading" :cols="5" :rows="5" />
        <tbody v-else>
          <tr v-if="!data?.data?.length">
            <td colspan="5" class="py-16 text-center text-text-muted text-sm">暂无账户</td>
          </tr>
          <tr v-for="acc in data?.data" :key="acc.id" class="group">
            <td class="font-medium">{{ acc.name }}</td>
            <td><span class="badge badge-muted font-mono">{{ acc.type }}</span></td>
            <td class="hidden md:table-cell text-text-muted">{{ acc.domain_count ?? 0 }}</td>
            <td><StatusBadge :status="acc.status" /></td>
            <td class="text-right">
              <div class="flex justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <button class="btn-icon"><Pencil class="w-3.5 h-3.5" /></button>
                <button class="btn-icon hover:text-danger" @click="deleteTarget = acc"><Trash2 class="w-3.5 h-3.5" /></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <SlideOver v-model:open="showAddSlide" title="添加 DNS 账户">
      <form class="space-y-4">
        <div>
          <label class="label">账户名称</label>
          <input v-model="addForm.name" class="input" placeholder="如：阿里云主账号" required />
        </div>
        <div>
          <label class="label">服务商类型</label>
          <input v-model="addForm.type" class="input" placeholder="如：aliyun, dnspod, cloudflare" required />
        </div>
        <div>
          <label class="label">配置（JSON）</label>
          <textarea v-model="addForm.config" class="input font-mono text-xs" rows="6" placeholder='{"key": "...", "secret": "..."}' />
        </div>
      </form>
      <template #footer>
        <div class="flex gap-3">
          <button class="btn-outline flex-1" @click="showAddSlide = false">取消</button>
          <button class="btn-primary flex-1" :disabled="addMutation.isPending.value" @click="addMutation.mutate()">
            <span v-if="addMutation.isPending.value" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
            添加
          </button>
        </div>
      </template>
    </SlideOver>

    <ConfirmDialog
      :open="!!deleteTarget"
      title="删除账户"
      :message="`确定删除账户 ${deleteTarget?.name}？此操作不可撤销。`"
      :loading="deleteMutation.isPending.value"
      @update:open="(v) => { if (!v) deleteTarget = null }"
      @confirm="deleteTarget && deleteMutation.mutate(deleteTarget.id)"
    />
  </div>
</template>
