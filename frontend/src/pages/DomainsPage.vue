<script setup lang="ts">
import { ref, computed } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { useRouter } from 'vue-router'
import { domainsApi, accountsApi, type DomainItem } from '@/api'
import { useToast } from '@/composables/useToast'
import { Globe, Plus, Search, RefreshCw, Trash2, ChevronRight, ExternalLink } from 'lucide-vue-next'
import StatusBadge from '@/components/shared/StatusBadge.vue'
import SkeletonRow from '@/components/shared/SkeletonRow.vue'
import SlideOver from '@/components/shared/SlideOver.vue'
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue'

const router = useRouter()
const toast = useToast()
const qc = useQueryClient()

const search = ref('')
const showAddSlide = ref(false)
const deleteTarget = ref<DomainItem | null>(null)
const syncing = ref<Set<number>>(new Set())

// Form state
const addForm = ref({ account_id: '', name: '' })

const { data: accounts } = useQuery({
  queryKey: ['accounts'],
  queryFn: async () => (await accountsApi.list()).data.data ?? [],
})

const { data, isLoading, refetch } = useQuery({
  queryKey: ['domains', 'list'],
  queryFn: async () => (await domainsApi.list()).data,
})

const filtered = computed(() => {
  const items = data.value?.data ?? []
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

    <!-- Search -->
    <div class="relative mb-5 max-w-sm">
      <Search class="w-4 h-4 text-text-disabled absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
      <input
        v-model="search"
        class="input pl-9"
        placeholder="搜索域名..."
      />
    </div>

    <!-- Table -->
    <div class="border border-border rounded-xl overflow-hidden">
      <table class="data-table">
        <thead>
          <tr>
            <th>域名</th>
            <th class="hidden sm:table-cell">账户</th>
            <th class="hidden md:table-cell">记录数</th>
            <th>状态</th>
            <th class="text-right">操作</th>
          </tr>
        </thead>
        <SkeletonRow v-if="isLoading" :cols="5" :rows="6" />
        <tbody v-else>
          <tr v-if="!filtered.length">
            <td colspan="5" class="py-16 text-center text-text-muted text-sm">
              {{ search ? '没有匹配的域名' : '暂无域名，点击"添加域名"开始' }}
            </td>
          </tr>
          <tr v-for="domain in filtered" :key="domain.id" class="cursor-pointer" @click="router.push(`/domains/${domain.id}/records`)">
            <td>
              <div class="flex items-center gap-2">
                <span class="text-text font-medium">{{ domain.name }}</span>
                <ExternalLink class="w-3 h-3 text-text-disabled opacity-0 group-hover:opacity-100" />
              </div>
            </td>
            <td class="hidden sm:table-cell text-text-muted">{{ domain.account_name || '-' }}</td>
            <td class="hidden md:table-cell text-text-muted">{{ domain.record_count ?? '-' }}</td>
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
  </div>
</template>
