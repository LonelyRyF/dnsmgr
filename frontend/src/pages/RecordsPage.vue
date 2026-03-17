<script setup lang="ts">
import { ref, computed, reactive } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { useRoute, useRouter } from 'vue-router'
import { recordsApi, domainsApi, type DnsRecord } from '@/api'
import { useToast } from '@/composables/useToast'
import {
  ArrowLeft, Plus, Search, Trash2, ToggleLeft, ToggleRight,
  Check, X, Pencil, RefreshCw, Filter, ListPlus, Settings2
} from 'lucide-vue-next'
import StatusBadge from '@/components/shared/StatusBadge.vue'
import SkeletonRow from '@/components/shared/SkeletonRow.vue'
import SlideOver from '@/components/shared/SlideOver.vue'
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const qc = useQueryClient()

const domainId = computed(() => Number(route.params.id))
const search = ref('')
const typeFilter = ref('')
const showAddSlide = ref(false)
const editingRecordId = ref<number | null>(null)
const deleteTarget = ref<DnsRecord | null>(null)
const selected = ref<Set<number>>(new Set())

// Batch Add state
const showBatchAddSlide = ref(false)
const batchAddText = ref('')
const batchAddConfig = reactive({
  type: '',
  line: '',
  ttl: 600,
  mx: 10,
  weight: 1,
  remark: ''
})

// Batch Modify state
const showBatchModifySlide = ref(false)
const batchModifyType = ref<'value'|'line'|'ttl'|'remark'>('value')
const batchModifyForm = reactive({
  type: 'A',
  value: '',
  line: '',
  ttl: 600,
  remark: ''
})

// Inline edit buffer
const editBuf = reactive({ name: '', value: '', ttl: 600, mx: 10, weight: 1 })

// New record form
const newRecord = reactive({
  name: '',
  type: 'A',
  value: '',
  line: '',
  ttl: 600,
  mx: 10,
  weight: 1,
  remark: '',
})

const RECORD_TYPES = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'CAA', 'REDIRECT_URL', 'FORWARD_URL']

const { data: domain } = useQuery({
  queryKey: ['domain', domainId],
  queryFn: async () => (await domainsApi.detail(domainId.value)).data.data,
})

const { data, isLoading, refetch } = useQuery({
  queryKey: ['records', domainId],
  queryFn: async () => (await recordsApi.list(domainId.value)).data.data,
})

const recordTypes = computed(() => {
  const types = new Set((data.value?.items ?? []).map((r: DnsRecord) => r.type))
  return Array.from(types).sort()
})

const filtered = computed(() => {
  let items = data.value?.items ?? []
  if (typeFilter.value) items = items.filter((r: DnsRecord) => r.type === typeFilter.value)
  const q = search.value.toLowerCase()
  if (q) items = items.filter((r: DnsRecord) =>
    r.name.toLowerCase().includes(q) || r.value.toLowerCase().includes(q)
  )
  return items
})

// ─── Inline edit ──────────────────────────────────────────────────────────────
function startEdit(record: DnsRecord) {
  editingRecordId.value = record.id
  editBuf.name = record.name
  editBuf.value = record.value
  editBuf.ttl = record.ttl
  editBuf.mx = record.mx ?? 10
  editBuf.weight = record.weight ?? 1
}

function cancelEdit() {
  editingRecordId.value = null
}

const updateMutation = useMutation({
  mutationFn: ({ record, payload }: { record: DnsRecord; payload: object }) =>
    recordsApi.update(domainId.value, record.id, payload),
  // Optimistic update
  onMutate: async ({ record, payload }) => {
    await qc.cancelQueries({ queryKey: ['records', domainId.value] })
    const prev = qc.getQueryData<typeof data.value>(['records', domainId.value])
    qc.setQueryData(['records', domainId.value], (old: typeof data.value) => ({
      ...old,
      items: old?.items?.map((r: DnsRecord) => r.id === record.id ? { ...r, ...payload } : r),
    }))
    return { prev }
  },
  onError: (_err, _vars, ctx) => {
    if (ctx?.prev) qc.setQueryData(['records', domainId.value], ctx.prev)
    toast.error('更新失败')
  },
  onSuccess: () => {
    editingRecordId.value = null
    toast.success('记录已更新')
  },
})

function saveEdit(record: DnsRecord) {
  updateMutation.mutate({
    record,
    payload: { name: editBuf.name, value: editBuf.value, ttl: editBuf.ttl, mx: editBuf.mx, weight: editBuf.weight },
  })
}

// ─── Toggle status (optimistic) ───────────────────────────────────────────────
const toggleMutation = useMutation({
  mutationFn: (record: DnsRecord) => recordsApi.toggleStatus(domainId.value, record.id),
  onMutate: async (record) => {
    await qc.cancelQueries({ queryKey: ['records', domainId.value] })
    const prev = qc.getQueryData(['records', domainId.value])
    qc.setQueryData(['records', domainId.value], (old: typeof data.value) => ({
      ...old,
      items: old?.items?.map((r: DnsRecord) => r.id === record.id ? { ...r, status: r.status === 1 ? 0 : 1 } : r),
    }))
    return { prev }
  },
  onError: (_err, _vars, ctx) => {
    if (ctx?.prev) qc.setQueryData(['records', domainId.value], ctx.prev)
    toast.error('状态切换失败，已回滚')
  },
  onSuccess: () => toast.success('状态已更新'),
})

// ─── Add record ───────────────────────────────────────────────────────────────
const addMutation = useMutation({
  mutationFn: () => recordsApi.create(domainId.value, { ...newRecord }),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['records', domainId.value] })
    showAddSlide.value = false
    Object.assign(newRecord, { name: '', type: 'A', value: '', line: '', ttl: 600, mx: 10, weight: 1, remark: '' })
    toast.success('记录已添加')
  },
  onError: (e: unknown) => {
    const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message || '添加失败'
    toast.error(msg)
  },
})

// ─── Delete record ────────────────────────────────────────────────────────────
const deleteMutation = useMutation({
  mutationFn: (record: DnsRecord) => recordsApi.delete(domainId.value, record.id),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['records', domainId.value] })
    deleteTarget.value = null
    toast.success('记录已删除')
  },
  onError: () => toast.error('删除失败'),
})

// ─── Batch Add records ────────────────────────────────────────────────────────
const batchAddMutation = useMutation({
  mutationFn: (payload: { records: object[] }) => recordsApi.batchCreate(domainId.value, payload),
  onSuccess: (res) => {
    qc.invalidateQueries({ queryKey: ['records', domainId.value] })
    showBatchAddSlide.value = false
    batchAddText.value = ''
    toast.success(`成功添加 ${res.data.data.success_count} 条，失败 ${res.data.data.failed_count} 条`)
  },
  onError: () => toast.error('批量添加失败'),
})

function submitBatchAdd() {
  const lines = batchAddText.value.split('\n').map(l => l.trim()).filter(Boolean)
  const records = lines.map(lineStr => {
    const parts = lineStr.split(/\s+/)
    const name = parts[0]
    const value = parts.slice(1).join(' ')
    let type = batchAddConfig.type
    if (!type) {
      if (/^[0-9.]+$/.test(value)) type = 'A'
      else if (value.includes(':')) type = 'AAAA'
      else type = 'CNAME'
    }
    return { name, value, type, line: batchAddConfig.line, ttl: batchAddConfig.ttl, mx: batchAddConfig.mx, weight: batchAddConfig.weight, remark: batchAddConfig.remark }
  })
  batchAddMutation.mutate({ records })
}

// ─── Batch Modify records ─────────────────────────────────────────────────────
const batchModifyMutation = useMutation({
  mutationFn: (payload: { action: string, type?: string, value?: string, line?: string, ttl?: number, remark?: string, records: object[] }) => {
    if (payload.action === 'remark') return recordsApi.batchOperation(domainId.value, payload)
    return recordsApi.batchUpdate(domainId.value, payload)
  },
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['records', domainId.value] })
    showBatchModifySlide.value = false
    selected.value.clear()
    toast.success('批量修改成功')
  },
  onError: () => toast.error('批量修改失败'),
})

function submitBatchModify() {
  const recordsPayload = filtered.value
    .filter((r: DnsRecord) => selected.value.has(r.id))
    .map((r: DnsRecord) => ({ recordid: r.id, name: r.name, type: r.type, value: r.value, line: r.line, ttl: r.ttl, mx: r.mx, weight: r.weight, remark: r.remark }))
    
  const act = batchModifyType.value
  const p = { action: act, records: recordsPayload } as any
  if (act === 'value') { p.type = batchModifyForm.type; p.value = batchModifyForm.value }
  else if (act === 'line') { p.line = batchModifyForm.line }
  else if (act === 'ttl') { p.ttl = batchModifyForm.ttl }
  else if (act === 'remark') { p.remark = batchModifyForm.remark }
  
  batchModifyMutation.mutate(p)
}

// ─── Batch operations ─────────────────────────────────────────────────────────
function toggleSelectAll() {
  if (selected.value.size === filtered.value.length) {
    selected.value.clear()
  } else {
    filtered.value.forEach((r: DnsRecord) => selected.value.add(r.id))
  }
}

function toggleSelect(id: number) {
  if (selected.value.has(id)) selected.value.delete(id)
  else selected.value.add(id)
}

async function batchOperation(op: string) {
  if (!selected.value.size) return
  if (op === 'delete' && !confirm(`确定删除选中的 ${selected.value.size} 条记录吗？`)) return
  
  const recordsPayload = filtered.value
    .filter((r: DnsRecord) => selected.value.has(r.id))
    .map((r: DnsRecord) => ({ recordid: r.id }))
    
  try {
    await recordsApi.batchOperation(domainId.value, {
      records: recordsPayload,
      action: op,
    })
    qc.invalidateQueries({ queryKey: ['records', domainId.value] })
    selected.value.clear()
    toast.success(`批量${op === 'open' ? '启用' : op === 'pause' ? '暂停' : '删除'}完成`)
  } catch {
    toast.error('批量操作失败')
  }
}
</script>

<template>
  <div>
    <!-- Header -->
    <div class="page-header flex-wrap gap-3">
      <div class="flex items-center gap-3">
        <button class="btn-icon" @click="router.push('/domains')">
          <ArrowLeft class="w-4 h-4" />
        </button>
        <div>
          <h1 class="page-title">{{ domain?.name ?? 'DNS 记录' }}</h1>
          <p class="page-subtitle">管理该域名下的所有解析记录</p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button class="btn-ghost btn-sm" @click="refetch()">
          <RefreshCw class="w-3.5 h-3.5" />
          刷新
        </button>
        <button class="btn-outline btn-sm" @click="showBatchAddSlide = true">
          <ListPlus class="w-3.5 h-3.5 shrink-0" />
          <span class="hidden sm:inline">批量添加</span>
        </button>
        <button class="btn-primary btn-sm" @click="showAddSlide = true">
          <Plus class="w-3.5 h-3.5" />
          添加记录
        </button>
      </div>
    </div>

    <!-- Filters row -->
    <div class="flex items-center gap-3 mb-5 flex-wrap">
      <!-- Search -->
      <div class="relative flex-1 min-w-0 max-w-xs">
        <Search class="w-4 h-4 text-text-disabled absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
        <input v-model="search" class="input pl-9" placeholder="搜索记录..." />
      </div>

      <!-- Type filter chips -->
      <div class="flex items-center gap-1.5 flex-wrap">
        <button
          class="badge cursor-pointer transition-colors"
          :class="typeFilter === '' ? 'badge-info' : 'badge-muted hover:bg-bg-active'"
          @click="typeFilter = ''"
        >全部</button>
        <button
          v-for="type in recordTypes"
          :key="type"
          class="badge cursor-pointer transition-colors"
          :class="typeFilter === type ? 'badge-info' : 'badge-muted hover:bg-bg-active'"
          @click="typeFilter = typeFilter === type ? '' : type"
        >{{ type }}</button>
      </div>
    </div>

    <!-- Batch toolbar -->
    <Transition name="fade">
      <div
        v-if="selected.size > 0"
        class="flex items-center gap-3 px-4 py-2.5 rounded-lg bg-accent/10 border border-accent/20 mb-4"
      >
        <span class="text-sm text-accent font-medium">已选 {{ selected.size }} 条</span>
        <div class="flex gap-2 ml-auto">
          <button class="btn-sm btn-outline" @click="showBatchModifySlide = true">
            <Settings2 class="w-3.5 h-3.5 mr-1" />
            批量修改配置
          </button>
          <button class="btn-sm btn-outline text-success border-success/30 hover:bg-success/10" @click="batchOperation('open')">批量启用</button>
          <button class="btn-sm btn-outline text-warn border-warn/30 hover:bg-warn/10" @click="batchOperation('pause')">批量暂停</button>
          <button class="btn-sm btn-danger" @click="batchOperation('delete')">批量删除</button>
        </div>
      </div>
    </Transition>

    <!-- Table -->
    <div class="border border-border rounded-xl overflow-hidden">
      <table class="data-table">
        <thead>
          <tr>
            <th class="w-8">
              <input
                type="checkbox"
                class="accent-accent w-3.5 h-3.5 cursor-pointer"
                :checked="selected.size > 0 && selected.size === filtered.length"
                :indeterminate="selected.size > 0 && selected.size < filtered.length"
                @change="toggleSelectAll"
              />
            </th>
            <th>主机记录</th>
            <th>类型</th>
            <th>值</th>
            <th class="hidden lg:table-cell">线路</th>
            <th class="hidden md:table-cell">TTL</th>
            <th>状态</th>
            <th class="text-right">操作</th>
          </tr>
        </thead>
        <SkeletonRow v-if="isLoading" :cols="8" :rows="8" />
        <tbody v-else>
          <tr v-if="!filtered.length">
            <td colspan="8" class="py-16 text-center text-text-muted text-sm">
              {{ search || typeFilter ? '没有匹配的记录' : '暂无记录，点击"添加记录"开始' }}
            </td>
          </tr>
          <tr
            v-for="record in filtered"
            :key="record.id"
            class="group"
            :class="{ 'bg-accent/5': selected.has(record.id) }"
          >
            <td @click.stop>
              <input
                type="checkbox"
                class="accent-accent w-3.5 h-3.5 cursor-pointer"
                :checked="selected.has(record.id)"
                @change="toggleSelect(record.id)"
              />
            </td>

            <!-- Inline editing cells -->
            <template v-if="editingRecordId === record.id">
              <td>
                <input v-model="editBuf.name" class="input py-1 text-xs w-28" />
              </td>
              <td>
                <span class="badge badge-muted">{{ record.type }}</span>
              </td>
              <td class="flex flex-col gap-1">
                <input v-model="editBuf.value" class="input py-1 text-xs w-48" />
                <div v-if="record.type === 'MX'" class="flex items-center gap-1">
                  <span class="text-xs text-text-muted">MX:</span>
                  <input v-model.number="editBuf.mx" type="number" class="input py-1 text-xs w-16 px-1" />
                </div>
                <div v-if="['A', 'CNAME', 'AAAA'].includes(record.type)" class="flex items-center gap-1">
                  <span class="text-xs text-text-muted">权重:</span>
                  <input v-model.number="editBuf.weight" type="number" class="input py-1 text-xs w-16 px-1" />
                </div>
              </td>
              <td class="hidden lg:table-cell text-text-muted text-xs">{{ record.line || 'default' }}</td>
              <td class="hidden md:table-cell">
                <input v-model.number="editBuf.ttl" type="number" class="input py-1 text-xs w-20" />
              </td>
              <td><StatusBadge :status="record.status" /></td>
              <td class="text-right">
                <div class="flex items-center justify-end gap-1">
                  <button
                    class="btn-icon text-success"
                    @click="saveEdit(record)"
                    :disabled="updateMutation.isPending.value"
                    title="保存"
                  >
                    <Check class="w-3.5 h-3.5" />
                  </button>
                  <button class="btn-icon" @click="cancelEdit" title="取消">
                    <X class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </template>

            <!-- Normal view cells -->
            <template v-else>
              <td class="font-mono text-xs text-text">{{ record.name || '@' }}</td>
              <td>
                <span class="badge badge-muted font-mono">{{ record.type }}</span>
              </td>
              <td class="max-w-xs">
                <div class="flex flex-col">
                  <span class="text-text-muted text-xs font-mono truncate block" :title="record.value">
                    {{ record.value }}
                  </span>
                  <div class="flex gap-2">
                    <span v-if="record.type === 'MX' && record.mx" class="text-[10px] text-accent/80">MX: {{ record.mx }}</span>
                    <span v-if="record.weight && record.weight > 0" class="text-[10px] text-accent/80">权重: {{ record.weight }}</span>
                    <span v-if="record.remark" class="text-[10px] text-text-disabled truncate max-w-[150px]" :title="record.remark">{{ record.remark }}</span>
                  </div>
                </div>
              </td>
              <td class="hidden lg:table-cell text-text-muted text-xs">{{ record.line || 'default' }}</td>
              <td class="hidden md:table-cell text-text-muted text-xs">{{ record.ttl }}s</td>
              <td>
                <button @click="toggleMutation.mutate(record)" class="hover:scale-105 transition-transform">
                  <StatusBadge :status="record.status" />
                </button>
              </td>
              <td class="text-right">
                <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                  <button class="btn-icon" @click="startEdit(record)" title="编辑">
                    <Pencil class="w-3.5 h-3.5" />
                  </button>
                  <button
                    class="btn-icon"
                    :title="record.status === 1 ? '暂停' : '启用'"
                    @click="toggleMutation.mutate(record)"
                  >
                    <ToggleLeft v-if="record.status === 0" class="w-3.5 h-3.5 text-text-disabled" />
                    <ToggleRight v-else class="w-3.5 h-3.5 text-success" />
                  </button>
                  <button class="btn-icon hover:text-danger" @click="deleteTarget = record" title="删除">
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </template>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Footer stats -->
    <div v-if="data" class="mt-3 text-xs text-text-muted">
      共 {{ data.items?.length ?? 0 }} 条记录
      <span v-if="search || typeFilter">（已过滤：{{ filtered.length }} 条）</span>
    </div>

    <!-- Add record slide-over -->
    <SlideOver v-model:open="showAddSlide" title="添加 DNS 记录">
      <form @submit.prevent="addMutation.mutate()" class="space-y-4">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="label">记录类型</label>
            <select v-model="newRecord.type" class="input">
              <option v-for="t in RECORD_TYPES" :key="t" :value="t">{{ t }}</option>
            </select>
          </div>
          <div>
            <label class="label">主机记录</label>
            <input v-model="newRecord.name" class="input" placeholder="@ 或 www" />
          </div>
        </div>
        <div>
          <label class="label">记录值</label>
          <input v-model="newRecord.value" class="input" placeholder="IP 地址或域名" required />
        </div>
        <div class="grid grid-cols-2 gap-3" v-if="newRecord.type === 'MX' || ['A','CNAME','AAAA'].includes(newRecord.type)">
          <div v-if="newRecord.type === 'MX'">
            <label class="label">MX 优先级</label>
            <input v-model.number="newRecord.mx" type="number" class="input" />
          </div>
          <div v-if="['A','CNAME','AAAA'].includes(newRecord.type)">
            <label class="label">权重</label>
            <input v-model.number="newRecord.weight" type="number" class="input" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="label">TTL (秒)</label>
            <input v-model.number="newRecord.ttl" type="number" class="input" />
          </div>
          <div>
            <label class="label">线路</label>
            <input v-model="newRecord.line" class="input" placeholder="默认" />
          </div>
        </div>
        <div>
          <label class="label">备注（可选）</label>
          <input v-model="newRecord.remark" class="input" placeholder="备注信息" />
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

    <!-- Batch Add record slide-over -->
    <SlideOver v-model:open="showBatchAddSlide" title="批量添加记录">
      <form @submit.prevent="submitBatchAdd" class="space-y-4">
        <div>
          <label class="label">主机记录和记录值</label>
          <textarea
            v-model="batchAddText"
            class="input font-mono text-sm leading-relaxed"
            rows="8"
            placeholder="主机记录 值&#10;@ 1.2.3.4&#10;www 1.2.3.4"
            required
          ></textarea>
          <p class="text-xs text-text-muted mt-1">每行一条，主机记录和值用空格分开。</p>
        </div>
        
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="label">记录类型 (默认自动识别)</label>
            <select v-model="batchAddConfig.type" class="input">
              <option value="">自动识别 A/CNAME/AAAA</option>
              <option v-for="t in RECORD_TYPES" :key="t" :value="t">{{ t }}</option>
            </select>
          </div>
          <div>
            <label class="label">线路</label>
            <input v-model="batchAddConfig.line" class="input" placeholder="默认" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3" v-if="batchAddConfig.type === 'MX' || ['A','CNAME','AAAA',''].includes(batchAddConfig.type)">
          <div v-if="batchAddConfig.type === 'MX'">
            <label class="label">MX 优先级</label>
            <input v-model.number="batchAddConfig.mx" type="number" class="input" />
          </div>
          <div v-if="['A','CNAME','AAAA',''].includes(batchAddConfig.type)">
            <label class="label">权重</label>
            <input v-model.number="batchAddConfig.weight" type="number" class="input" />
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="label">TTL (秒)</label>
            <input v-model.number="batchAddConfig.ttl" type="number" class="input" />
          </div>
          <div>
            <label class="label">备注（可选）</label>
            <input v-model="batchAddConfig.remark" class="input" placeholder="备注信息" />
          </div>
        </div>
      </form>
      <template #footer>
        <div class="flex gap-3">
          <button class="btn-outline flex-1" @click="showBatchAddSlide = false">取消</button>
          <button
            class="btn-primary flex-1"
            :disabled="batchAddMutation.isPending.value || !batchAddText"
            @click="submitBatchAdd"
          >
            <span v-if="batchAddMutation.isPending.value" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
            批量添加
          </button>
        </div>
      </template>
    </SlideOver>

    <!-- Batch Modify slide-over -->
    <SlideOver v-model:open="showBatchModifySlide" title="批量修改选中的记录">
      <form @submit.prevent="submitBatchModify" class="space-y-4">
        <div>
          <label class="label">修改目标字段</label>
          <select v-model="batchModifyType" class="input">
            <option value="value">记录类型 / 值</option>
            <option value="line">线路</option>
            <option value="ttl">TTL时间</option>
            <option value="remark">备注</option>
          </select>
        </div>

        <template v-if="batchModifyType === 'value'">
          <div>
            <label class="label">新记录类型</label>
            <select v-model="batchModifyForm.type" class="input">
              <option v-for="t in RECORD_TYPES" :key="t" :value="t">{{ t }}</option>
            </select>
          </div>
          <div>
            <label class="label">新记录值</label>
            <input v-model="batchModifyForm.value" class="input" required />
          </div>
        </template>
        
        <template v-if="batchModifyType === 'line'">
          <div>
            <label class="label">新线路</label>
            <input v-model="batchModifyForm.line" class="input" required />
          </div>
        </template>
        
        <template v-if="batchModifyType === 'ttl'">
          <div>
            <label class="label">新 TTL (秒)</label>
            <input v-model.number="batchModifyForm.ttl" type="number" class="input" required />
          </div>
        </template>
        
        <template v-if="batchModifyType === 'remark'">
          <div>
            <label class="label">新备注</label>
            <input v-model="batchModifyForm.remark" class="input" placeholder="留空则清空备注" />
          </div>
        </template>

      </form>
      <template #footer>
        <div class="flex gap-3">
          <button class="btn-outline flex-1" @click="showBatchModifySlide = false">取消</button>
          <button
            class="btn-primary flex-1"
            :disabled="batchModifyMutation.isPending.value"
            @click="submitBatchModify"
          >
            保存修改
          </button>
        </div>
      </template>
    </SlideOver>

    <!-- Delete confirmation -->
    <ConfirmDialog
      :open="!!deleteTarget"
      title="删除记录"
      :message="`确定删除记录 ${deleteTarget?.name || '@'} → ${deleteTarget?.value}？`"
      confirm-label="删除"
      :loading="deleteMutation.isPending.value"
      @update:open="(v) => { if (!v) deleteTarget = null }"
      @confirm="deleteTarget && deleteMutation.mutate(deleteTarget)"
    />
  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: all 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; transform: translateY(-4px); }
</style>
