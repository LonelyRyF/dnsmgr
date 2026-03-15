<script setup lang="ts">
import { ref, computed } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { certificatesApi, type Certificate } from '@/api'
import { useToast } from '@/composables/useToast'
import { ShieldCheck, Plus, Trash2, RefreshCw, Clock } from 'lucide-vue-next'
import StatusBadge from '@/components/shared/StatusBadge.vue'
import SkeletonRow from '@/components/shared/SkeletonRow.vue'
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue'
import SlideOver from '@/components/shared/SlideOver.vue'

const toast = useToast()
const qc = useQueryClient()
const deleteTarget = ref<Certificate | null>(null)
const showAddSlide = ref(false)
const addForm = ref({ domain: '', account_id: '' })

const { data: accounts } = useQuery({ queryKey: ['cert-accounts'], queryFn: async () => (await certificatesApi.accountList()).data })
const { data, isLoading, refetch } = useQuery({
  queryKey: ['certificates'],
  queryFn: async () => (await certificatesApi.list()).data,
})

const certStatusLabels: Record<number, string> = {
  1: '处理中', 2: '等待验证', 3: '已签发', 4: '续签中', 5: '签发失败', 6: '即将过期', 7: '已过期',
}

const addMutation = useMutation({
  mutationFn: () => certificatesApi.create(addForm.value),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['certificates'] })
    showAddSlide.value = false
    toast.success('证书申请已提交')
  },
  onError: () => toast.error('提交失败'),
})

const deleteMutation = useMutation({
  mutationFn: (id: number) => certificatesApi.delete(id),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['certificates'] })
    deleteTarget.value = null
    toast.success('证书已删除')
  },
  onError: () => toast.error('删除失败'),
})

function certStatusStyle(status: number) {
  if (status === 3) return 'badge-success'
  if (status === 5 || status === 7) return 'badge-danger'
  if (status === 6) return 'badge-warn'
  return 'badge-info'
}
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title flex items-center gap-2.5">
          <ShieldCheck class="w-5 h-5 text-success" /> SSL 证书管理
        </h1>
        <p class="page-subtitle">管理 SSL/TLS 证书的申请、续签与部署</p>
      </div>
      <div class="flex gap-2">
        <button class="btn-ghost btn-sm" @click="refetch()"><RefreshCw class="w-3.5 h-3.5" /> 刷新</button>
        <button class="btn-primary btn-sm" @click="showAddSlide = true"><Plus class="w-3.5 h-3.5" /> 申请证书</button>
      </div>
    </div>

    <div class="border border-border rounded-xl overflow-hidden">
      <table class="data-table">
        <thead>
          <tr>
            <th>域名</th>
            <th>状态</th>
            <th class="hidden md:table-cell">过期时间</th>
            <th class="hidden sm:table-cell">创建时间</th>
            <th class="text-right">操作</th>
          </tr>
        </thead>
        <SkeletonRow v-if="isLoading" :cols="5" :rows="6" />
        <tbody v-else>
          <tr v-if="!data?.data?.length">
            <td colspan="5" class="py-16 text-center text-text-muted text-sm">暂无证书，点击申请证书开始</td>
          </tr>
          <tr v-for="cert in data?.data" :key="cert.id" class="group">
            <td class="font-medium font-mono">{{ cert.domain }}</td>
            <td>
              <span :class="['badge', certStatusStyle(cert.status)]">
                <span class="inline-block w-1.5 h-1.5 rounded-full bg-current" />
                {{ certStatusLabels[cert.status] ?? cert.status }}
              </span>
            </td>
            <td class="hidden md:table-cell text-text-muted text-xs">
              <span v-if="cert.expire_time" class="flex items-center gap-1">
                <Clock class="w-3 h-3" /> {{ cert.expire_time }}
              </span>
              <span v-else>—</span>
            </td>
            <td class="hidden sm:table-cell text-text-muted text-xs">{{ cert.created_at }}</td>
            <td class="text-right">
              <div class="flex justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <button class="btn-icon hover:text-danger" @click="deleteTarget = cert"><Trash2 class="w-3.5 h-3.5" /></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <SlideOver v-model:open="showAddSlide" title="申请 SSL 证书">
      <form class="space-y-4">
        <div>
          <label class="label">证书账户</label>
          <select v-model="addForm.account_id" class="input" required>
            <option value="">请选择账户</option>
            <option v-for="acc in accounts?.data" :key="acc.id" :value="acc.id">{{ acc.id }}</option>
          </select>
        </div>
        <div>
          <label class="label">域名</label>
          <input v-model="addForm.domain" class="input" placeholder="example.com 或 *.example.com" required />
        </div>
      </form>
      <template #footer>
        <div class="flex gap-3">
          <button class="btn-outline flex-1" @click="showAddSlide = false">取消</button>
          <button class="btn-primary flex-1" :disabled="addMutation.isPending.value" @click="addMutation.mutate()">
            <span v-if="addMutation.isPending.value" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
            提交申请
          </button>
        </div>
      </template>
    </SlideOver>

    <ConfirmDialog
      :open="!!deleteTarget"
      title="删除证书"
      :message="`确定删除 ${deleteTarget?.domain} 的证书？`"
      :loading="deleteMutation.isPending.value"
      @update:open="(v) => { if (!v) deleteTarget = null }"
      @confirm="deleteTarget && deleteMutation.mutate(deleteTarget.id)"
    />
  </div>
</template>
