<script setup lang="ts">
import { ref, computed } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { useRouter } from 'vue-router'
import { domainsApi, accountsApi, type DomainItem } from '@/api'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import { Globe, Plus, Search, RefreshCw, Trash2, ChevronRight, ExternalLink, Settings, MessageSquare } from 'lucide-vue-next'

import StatusBadge from '@/components/shared/StatusBadge.vue'
import SkeletonRow from '@/components/shared/SkeletonRow.vue'
import SlideOver from '@/components/shared/SlideOver.vue'
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue'

const router = useRouter()
const toast = useToast()
const qc = useQueryClient()
const authStore = useAuthStore()

const search = ref('')
const showAddSlide = ref(false)
const deleteTarget = ref<DomainItem | null>(null)
const syncing = ref<Set<number>>(new Set())

// Batch ops state
const selectedIds = ref<Set<number>>(new Set())
const isAllSelected = computed(() => {
  if (!filtered.value.length) return false
  return selectedIds.value.size === filtered.value.length
})
const showRemarkSlide = ref(false)
const batchRemarkText = ref('')

const toggleAll = (e: Event) => {
  const checked = (e.target as HTMLInputElement).checked
  if (checked) {
    selectedIds.value = new Set(filtered.value.map(d => d.id))
  } else {
    selectedIds.value.clear()
  }
}

const toggleSelect = (id: number) => {
  if (selectedIds.value.has(id)) {
    selectedIds.value.delete(id)
  } else {
    selectedIds.value.add(id)
  }
}

// Form state
const addForm = ref({ account_id: '', name: '' })

const { data: accounts } = useQuery({
  queryKey: ['accounts'],
  queryFn: async () => (await accountsApi.list()).data.data ?? [],
})

const { data, isLoading, refetch } = useQuery({
  queryKey: ['domains', 'list'],
  queryFn: async () => (await domainsApi.list()).data.data,
})

const filtered = computed(() => {
  const items = data.value?.items ?? []
  const q = search.value.toLowerCase()
  return q ? items.filter(d => d.name.toLowerCase().includes(q)) : items
})

// Add domain
const addMutation = useMutation({
  mutationFn: () => domainsApi.create(addForm.value),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['domains'] })
    showAddSlide.value = false
    addForm.value = { account_id: '', name: '' }
    toast.success('域名已添加')
  },
  onError: (e: unknown) => {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message || '添加失败'
    toast.error(msg)
  },
})

// Sync domain
async function syncDomain(domain: DomainItem) {
  syncing.value.add(domain.id)
  try {
    await domainsApi.sync(domain.id)
    qc.invalidateQueries({ queryKey: ['domains'] })
    toast.success(`${domain.name} 同步完成`)
  } catch {
    toast.error('同步失败')
  } finally {
    syncing.value.delete(domain.id)
  }
}

// Delete domain
const deleteMutation = useMutation({
  mutationFn: (id: number) => domainsApi.delete(id),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['domains'] })
    deleteTarget.value = null
    toast.success('域名已删除')
  },
  onError: () => toast.error('删除失败'),
})

// Toggle domain settings
const updateMutation = useMutation({
  mutationFn: ({ id, data }: { id: number, data: object }) => domainsApi.update(id, data),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['domains'] })
  },
  onError: () => toast.error('更新设置失败')
})

const updateSetting = (domain: DomainItem, key: string, value: number) => {
  // Optimistic UI could go here, but doing it via mutation ensures real updates
  updateMutation.mutate({ id: domain.id, data: { [key]: value } })
}

// Batch operations
const batchMutation = useMutation({
  mutationFn: (data: { action: string, ids: number[], remark?: string }) => domainsApi.batchOperation(data),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['domains'] })
    toast.success('批量操作执行成功')
    selectedIds.value.clear()
    showRemarkSlide.value = false
    batchRemarkText.value = ''
  },
  onError: () => toast.error('批量操作执行失败')
})

const executeBatch = (action: string) => {
  if (selectedIds.value.size === 0) return
  if (action === 'delete') {
    if (!confirm(`确定要删除选中的 ${selectedIds.value.size} 个域名吗？`)) return
  }
  if (action === 'remark') {
    showRemarkSlide.value = true
    return
  }
  batchMutation.mutate({ action, ids: Array.from(selectedIds.value) })
}
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title flex items-center gap-2.5">
          <Globe class="w-5 h-5 text-accent" />
          域名管理
        </h1>
        <p class="page-subtitle">管理所有 DNS 域名及记录</p>
      </div>
      <div class="flex items-center gap-2">
        <button class="btn-ghost btn-sm" @click="refetch()">
          <RefreshCw class="w-3.5 h-3.5" />
          刷新
        </button>
        <button class="btn-primary btn-sm" @click="showAddSlide = true">
          <Plus class="w-3.5 h-3.5" />
          添加域名
        </button>
      </div>
    </div>

    <!-- Search and Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-5 gap-3">
      <div class="relative w-full max-w-sm shrink-0">
        <Search class="w-4 h-4 text-text-disabled absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
        <input
          v-model="search"
          class="input pl-9"
          placeholder="搜索域名..."
        />
      </div>
      
      <!-- Batch Actions Toolbar -->
      <div v-if="selectedIds.size > 0" class="flex items-center gap-2 animate-in fade-in slide-in-from-bottom-2 duration-200">
        <span class="text-sm text-text-muted mr-2">已悬 {{ selectedIds.size }} 项</span>
        <button class="btn-outline btn-sm" @click="executeBatch('notice_on')">开启提醒</button>
        <button class="btn-outline btn-sm" @click="executeBatch('notice_off')">关闭提醒</button>
        <button class="btn-outline btn-sm" @click="executeBatch('remark')">
          <MessageSquare class="w-3.5 h-3.5 mr-1" />
          批量备注
        </button>
        <button class="btn-outline btn-sm text-danger border-danger/20 hover:bg-danger/10" @click="executeBatch('delete')">
          <Trash2 class="w-3.5 h-3.5 mr-1" />
          批量删除
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="border border-border rounded-xl overflow-hidden">
      <table class="data-table">
        <thead>
          <tr>
            <th class="w-12 text-center">
              <input type="checkbox" class="checkbox" :checked="isAllSelected" @change="toggleAll" />
            </th>
            <th>域名</th>
            <th class="hidden sm:table-cell">账户</th>
            <th class="hidden md:table-cell">配置</th>
            <th>状态</th>
            <th class="text-right">操作</th>
          </tr>
        </thead>
        <SkeletonRow v-if="isLoading" :cols="6" :rows="6" />
        <tbody v-else>
          <tr v-if="!filtered.length">
            <td colspan="6" class="py-16 text-center text-text-muted text-sm">
              {{ search ? '没有匹配的域名' : '暂无域名，点击"添加域名"开始' }}
            </td>
          </tr>
          <tr v-for="domain in filtered" :key="domain.id" class="cursor-pointer" @click="router.push(`/domains/${domain.id}/records`)">
            <td class="text-center" @click.stop>
              <input type="checkbox" class="checkbox" :checked="selectedIds.has(domain.id)" @change="toggleSelect(domain.id)" />
            </td>
            <td>
              <div class="flex flex-col">
                <div class="flex items-center gap-2">
                  <span class="text-text font-medium">{{ domain.name }}</span>
                  <ExternalLink class="w-3 h-3 text-text-disabled opacity-0 group-hover:opacity-100" />
                </div>
                <div v-if="domain.remark" class="text-xs text-text-muted mt-0.5 truncate max-w-[200px]" :title="domain.remark">
                  {{ domain.remark }}
                </div>
              </div>
            </td>
            <td class="hidden sm:table-cell text-text-muted">
              {{ domain.account_name || '-' }}
              <div class="text-xs mt-0.5 opacity-60">记录: {{ domain.record_count ?? '-' }}</div>
            </td>
            <td class="hidden md:table-cell text-xs" @click.stop>
                <div class="flex flex-wrap gap-2 items-center">
                  <label class="flex items-center gap-1.5 cursor-pointer" title="到期提醒">
                    <input type="checkbox" class="checkbox w-3.5 h-3.5 rounded-sm" :checked="domain.is_notice === 1" @change="updateSetting(domain, 'is_notice', domain.is_notice === 1 ? 0 : 1)" />
                    <span class="text-text-muted">提醒</span>
                  </label>
                  <label class="flex items-center gap-1.5 cursor-pointer" title="SSO对接">
                    <input type="checkbox" class="checkbox w-3.5 h-3.5 rounded-sm" :checked="domain.is_sso === 1" @change="updateSetting(domain, 'is_sso', domain.is_sso === 1 ? 0 : 1)" />
                    <span class="text-text-muted">SSO</span>
                  </label>
                  <label v-if="authStore.isAdmin" class="flex items-center gap-1.5 cursor-pointer text-danger" title="隐藏域名(仅管理员可见)">
                    <input type="checkbox" class="checkbox w-3.5 h-3.5 rounded-sm checked:bg-danger checked:border-danger" :checked="domain.is_hide === 1" @change="updateSetting(domain, 'is_hide', domain.is_hide === 1 ? 0 : 1)" />
                    <span class="opacity-80">隐藏</span>
                  </label>
                </div>
            </td>
            <td><StatusBadge :status="domain.status" /></td>
            <td class="text-right" @click.stop>
              <div class="flex items-center justify-end gap-1">
                <button
                  class="btn-icon"
                  :class="{ 'opacity-50 cursor-not-allowed': syncing.has(domain.id) }"
                  :disabled="syncing.has(domain.id)"
                  @click="syncDomain(domain)"
                  title="同步记录"
                >
                  <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': syncing.has(domain.id) }" />
                </button>
                <button
                  class="btn-icon hover:text-danger"
                  @click="deleteTarget = domain"
                  title="删除域名"
                >
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
                <ChevronRight class="w-3.5 h-3.5 text-text-disabled" />
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add domain slide-over -->
    <SlideOver v-model:open="showAddSlide" title="添加域名">
      <form @submit.prevent="addMutation.mutate()" class="space-y-4">
        <div>
          <label class="label">DNS 账户</label>
          <select v-model="addForm.account_id" class="input" required>
            <option value="">请选择账户</option>
            <option
              v-for="acc in accounts"
              :key="acc.id"
              :value="acc.id"
            >{{ acc.name }} ({{ acc.type }})</option>
          </select>
        </div>
        <div>
          <label class="label">域名</label>
          <input
            v-model="addForm.name"
            class="input"
            placeholder="example.com"
            required
          />
        </div>
      </form>
      <template #footer>
        <div class="flex gap-3">
          <button class="btn-outline flex-1" @click="showAddSlide = false">取消</button>
          <button
            class="btn-primary flex-1"
            :disabled="addMutation.isPending.value"
            @click="addMutation.mutate()"
          >
            <span v-if="addMutation.isPending.value" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
            添加
          </button>
        </div>
      </template>
    </SlideOver>

    <!-- Delete confirmation -->
    <ConfirmDialog
      :open="!!deleteTarget"
      title="删除域名"
      :message="`确定要删除域名 ${deleteTarget?.name} 吗？此操作不可撤销。`"
      confirm-label="删除"
      :loading="deleteMutation.isPending.value"
      @update:open="(v) => { if (!v) deleteTarget = null }"
      @confirm="deleteTarget && deleteMutation.mutate(deleteTarget.id)"
    />

    <!-- Batch Remark Slide-over -->
    <SlideOver v-model:open="showRemarkSlide" title="批量修改备注">
      <form @submit.prevent="batchMutation.mutate({ action: 'remark', ids: Array.from(selectedIds), remark: batchRemarkText })" class="space-y-4">
        <div>
          <label class="label">给选中的 {{ selectedIds.size }} 个域名设置备注</label>
          <input
            v-model="batchRemarkText"
            class="input"
            placeholder="留空则清空备注"
          />
        </div>
      </form>
      <template #footer>
        <div class="flex gap-3">
          <button class="btn-outline flex-1" @click="showRemarkSlide = false">取消</button>
          <button
            class="btn-primary flex-1"
            :disabled="batchMutation.isPending.value"
            @click="batchMutation.mutate({ action: 'remark', ids: Array.from(selectedIds), remark: batchRemarkText })"
          >
            保存备注
          </button>
        </div>
      </template>
    </SlideOver>
  </div>
</template>
