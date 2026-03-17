<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { monitorApi, domainsApi, recordsApi, type MonitorTask } from '@/api'
import { useToast } from '@/composables/useToast'
import { Activity, RefreshCw, Plus, Trash2, Edit2, FileText, Check, X, ShieldAlert } from 'lucide-vue-next'
import StatusBadge from '@/components/shared/StatusBadge.vue'
import SkeletonRow from '@/components/shared/SkeletonRow.vue'
import SlideOver from '@/components/shared/SlideOver.vue'
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue'

const toast = useToast()
const qc = useQueryClient()

const selectedIds = ref<number[]>([])
const deleteTarget = ref<MonitorTask | null>(null)
const logTarget = ref<MonitorTask | null>(null)
const showFormSlide = ref(false)
const isEdit = ref(false)

const defaultForm = () => ({
  id: 0,
  did: '',
  rr: '',
  recordid: '',
  recordinfo: '',
  main_value: '',
  type: 1, // 1:暂停 2:切换备用 3:条件开启
  backup_value: '',
  checktype: 1, // 0:ping 1:tcp 2:http
  checkurl: '',
  tcpport: 80,
  frequency: 5,
  cycle: 3,
  timeout: 2,
  proxy: 0,
  cdn: 0,
  remark: ''
})

const formData = ref(defaultForm())
const fetchedRecords = ref<any[]>([])
const isFetchingRecords = ref(false)

const logPage = ref(1)
const { data: logsData, isLoading: logsLoading } = useQuery({
  queryKey: ['monitor-logs', computed(() => logTarget.value?.id), logPage],
  queryFn: async () => (await monitorApi.taskLogs(logTarget.value!.id, { page: logPage.value, page_size: 15 })).data,
  enabled: computed(() => !!logTarget.value)
})

const { data: domains } = useQuery({
  queryKey: ['domains'],
  queryFn: async () => (await domainsApi.list({ limit: 1000 })).data.data?.items || []
})

const { data: overview, isLoading: overviewLoading } = useQuery({
  queryKey: ['monitor-overview'],
  queryFn: async () => (await monitorApi.overview()).data.data,
  refetchInterval: 30_000,
})

const { data, isLoading, refetch } = useQuery({
  queryKey: ['monitor-tasks'],
  queryFn: async () => (await monitorApi.taskList()).data.data,
})

const saveMutation = useMutation({
  mutationFn: (param: any) => isEdit.value ? monitorApi.taskUpdate(param.id, param) : monitorApi.taskCreate(param),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['monitor-tasks'] })
    showFormSlide.value = false
    toast.success(isEdit.value ? '策略已更新' : '策略已保存')
  },
  onError: (err: any) => toast.error(err.response?.data?.error || '操作失败')
})

const deleteMutation = useMutation({
  mutationFn: (id: number) => monitorApi.taskDelete(id),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['monitor-tasks'] })
    deleteTarget.value = null
    toast.success('策略已删除')
  },
  onError: () => toast.error('删除失败')
})

const toggleMutation = useMutation({
  mutationFn: (id: number) => monitorApi.taskToggleActive(id),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['monitor-tasks'] })
    toast.success('状态已更新')
  },
  onError: () => toast.error('操作失败')
})

const batchMutation = useMutation({
  mutationFn: (data: object) => monitorApi.taskBatchOperation(data),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['monitor-tasks'] })
    selectedIds.value = []
    toast.success('批量操作成功')
  },
  onError: () => toast.error('批量操作失败')
})

function openAdd() {
  isEdit.value = false
  formData.value = defaultForm()
  fetchedRecords.value = []
  showFormSlide.value = true
}

function openEdit(task: MonitorTask) {
  isEdit.value = true
  formData.value = { ...defaultForm(), ...task }
  fetchedRecords.value = [{
    RecordId: task.recordid,
    Value: task.main_value,
    LineName: JSON.parse(task.recordinfo || '{}').LineName || '未知'
  }]
  showFormSlide.value = true
}

async function fetchRecords() {
  if (!formData.value.did) return toast.error('请先选择域名')
  if (!formData.value.rr) return toast.error('请输入主机记录')
  isFetchingRecords.value = true
  try {
    const res = await recordsApi.list(Number(formData.value.did), { keyword: formData.value.rr, page_size: 100 })
    fetchedRecords.value = res.data.data?.items || []
    if (fetchedRecords.value.length === 0) toast.error('未找到匹配的解析记录')
    else toast.success(`获取到 ${fetchedRecords.value.length} 条记录`)
  } catch (err) {
    toast.error('获取解析记录失败')
  } finally {
    isFetchingRecords.value = false
  }
}

watch(() => formData.value.recordid, (val) => {
  if (!val) return
  const rec = fetchedRecords.value.find(r => r.RecordId === val)
  if (rec) {
    formData.value.main_value = Array.isArray(rec.Value) ? rec.Value[0] : rec.Value
    formData.value.recordinfo = JSON.stringify({
      Line: rec.Line,
      LineName: rec.LineName,
      TTL: rec.TTL
    })
  }
})

const isAllSelected = computed(() => {
  return data.value?.items && data.value.items.length > 0 && selectedIds.value.length === data.value.items.length
})

function toggleSelectAll() {
  if (isAllSelected.value) selectedIds.value = []
  else selectedIds.value = data.value?.items?.map((t: MonitorTask) => t.id) || []
}
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
      <div class="flex gap-2">
        <button class="btn-ghost btn-sm" @click="refetch()"><RefreshCw class="w-3.5 h-3.5" /> 刷新</button>
        <button class="btn-primary btn-sm" @click="openAdd()"><Plus class="w-3.5 h-3.5" /> 添加策略</button>
      </div>
    </div>

    <!-- Global system warning -->
    <div v-if="overview?.run_state === 0" class="mb-4 p-4 bg-danger/10 border border-danger/30 rounded-xl text-danger flex items-start gap-3">
      <ShieldAlert class="w-5 h-5 shrink-0 mt-0.5" />
      <div>
        <h3 class="font-bold">系统离线监控未启动</h3>
        <p class="text-sm mt-1 opacity-90">当前未检测到监控系统的运行心跳。请在服务器配置自动任务以运行 <code>think run:monitor</code>，否则所有的容灾切换策略都不会执行。</p>
      </div>
    </div>

    <!-- Batch operations -->
    <div class="mb-4 flex gap-2 items-center bg-bg-mute px-3 py-2 rounded-lg" v-if="selectedIds.length > 0">
      <span class="text-sm font-medium mr-2">已选 {{ selectedIds.length }} 项</span>
      <button class="btn-ghost btn-sm text-success" @click="batchMutation.mutate({ action: 'open', ids: selectedIds })"><Check class="w-4 h-4 mr-1" /> 启用</button>
      <button class="btn-ghost btn-sm text-warn" @click="batchMutation.mutate({ action: 'close', ids: selectedIds })"><X class="w-4 h-4 mr-1" /> 停止</button>
      <button class="btn-ghost btn-sm" @click="batchMutation.mutate({ action: 'retry', ids: selectedIds })"><RefreshCw class="w-4 h-4 mr-1" /> 重试</button>
      <button class="btn-ghost btn-sm text-danger hover:bg-danger/10" @click="batchMutation.mutate({ action: 'delete', ids: selectedIds })"><Trash2 class="w-4 h-4 mr-1" /> 删除</button>
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
            <th class="w-10 text-center"><input type="checkbox" class="checkbox" :checked="isAllSelected" @change="toggleSelectAll" /></th>
            <th>记录名 (Host)</th>
            <th>监控设置</th>
            <th>协议</th>
            <th>检测间隔</th>
            <th>状态</th>
            <th>运行开关</th>
            <th class="hidden md:table-cell">最后检查</th>
            <th class="text-right">操作</th>
          </tr>
        </thead>
        <SkeletonRow v-if="isLoading" :cols="5" :rows="5" />
        <tbody v-else>
          <tr v-if="!data?.items?.length">
            <td colspan="5" class="py-16 text-center text-text-muted text-sm">暂无监控任务</td>
          </tr>
          <tr v-for="task in data?.items" :key="task.id">
            <td class="text-center"><input type="checkbox" class="checkbox" :value="task.id" v-model="selectedIds" /></td>
            <td>
              <div class="font-medium flex items-center gap-1">
                {{ task.rr }}.<span class="text-text-muted">{{ task.domain }}</span>
              </div>
              <div class="text-xs text-text-muted max-w-[12rem] truncate" :title="task.remark" v-if="task.remark">{{ task.remark }}</div>
            </td>
            <td>
              <div class="text-xs">
                <span class="badge badge-error" v-if="task.type === 1">暂停解析</span>
                <span class="badge badge-success" v-else-if="task.type === 2" :title="'备用IP: ' + task.backup_value">切换备用</span>
                <span class="badge badge-info" v-else-if="task.type === 3" :title="'触发条件: < ' + task.cycle">条件开启</span>
                <span class="badge badge-neutral" v-else>无操作</span>
              </div>
              <div class="font-mono text-xs text-text-muted mt-0.5 truncate max-w-[10rem]">{{ task.main_value }}</div>
            </td>
            <td>
              <span class="badge badge-neutral">
                {{ task.checktype === 0 ? 'PING' : task.checktype === 1 ? 'TCP' : 'HTTP/S' }}
              </span>
            </td>
            <td>{{ task.frequency }}s</td>
            <td><StatusBadge :status="task.status === 0 ? 1 : 0" active-label="正常" inactive-label="异常" /></td>
            <td>
              <button @click="toggleMutation.mutate(task.id)" :class="['relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors', task.active ? 'bg-success' : 'bg-border']">
                <span :class="['pointer-events-none inline-block h-4 w-4 transform rounded-full bg-bg shadow ring-0 transition duration-200 ease-in-out', task.active ? 'translate-x-4' : 'translate-x-0']" />
              </button>
            </td>
            <td class="hidden md:table-cell text-text-muted text-[11px]">{{ task.checktimestr ?? '未检查' }}</td>
            <td class="text-right">
              <div class="flex justify-end gap-1 opacity-100 transition-opacity">
                <button class="btn-icon text-primary" title="查看日志" @click="logPage = 1; logTarget = task"><FileText class="w-3.5 h-3.5" /></button>
                <button class="btn-icon" title="编辑策略" @click="openEdit(task)"><Edit2 class="w-3.5 h-3.5" /></button>
                <button class="btn-icon hover:text-danger" title="删除策略" @click="deleteTarget = task"><Trash2 class="w-3.5 h-3.5" /></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Form SlideOver -->
    <SlideOver v-model:open="showFormSlide" :title="isEdit ? '编辑策略' : '添加策略'" size="md">
      <form class="space-y-5" @submit.prevent>
        <!-- 主机及域名选择 -->
        <div class="grid grid-cols-[1fr_1.5fr] items-end gap-2">
          <div>
            <label class="label">主机记录 <span class="text-danger">*</span></label>
            <input v-model="formData.rr" class="input" placeholder="www" required />
          </div>
          <div>
            <label class="label">主域名 <span class="text-danger">*</span></label>
            <select v-model="formData.did" class="input" required>
              <option value="">-- 选择域名 --</option>
              <option v-for="d in domains" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>
        </div>

        <!-- 当前解析记录获取 -->
        <div>
          <label class="label">目标解析记录 <span class="text-danger">*</span></label>
          <div class="flex gap-2">
            <select v-model="formData.recordid" class="input flex-1" required>
              <option value="" disabled>-- 请先获取记录 --</option>
              <option v-for="rec in fetchedRecords" :key="rec.RecordId" :value="rec.RecordId">
                {{ Array.isArray(rec.Value) ? rec.Value[0] : rec.Value }} (线路: {{ rec.LineName || rec.Line }})
              </option>
            </select>
            <button class="btn-outline whitespace-nowrap" @click="fetchRecords" :disabled="isFetchingRecords">
              <RefreshCw v-if="isFetchingRecords" class="w-4 h-4 animate-spin mr-1" />
              {{ isFetchingRecords ? '获取中...' : '点击获取' }}
            </button>
          </div>
        </div>

        <!-- 切换类型 -->
        <div>
          <label class="label">切换类型 <span class="text-danger">*</span></label>
          <div class="flex flex-wrap gap-4 mt-1">
            <label class="flex items-center gap-1.5 cursor-pointer">
              <input type="radio" value="1" v-model="formData.type" class="radio"> 暂停解析
            </label>
            <label class="flex items-center gap-1.5 cursor-pointer">
              <input type="radio" value="2" v-model="formData.type" class="radio"> 切换备用IP
            </label>
            <label class="flex items-center gap-1.5 cursor-pointer">
              <input type="radio" value="3" v-model="formData.type" class="radio"> 条件开启 (暂停配合使用)
            </label>
          </div>
        </div>

        <!-- 备用解析记录 -->
        <div v-if="formData.type === 2">
          <label class="label">备用解析记录 <span class="text-danger">*</span></label>
          <input v-model="formData.backup_value" class="input font-mono" placeholder="当主IP宕机时切换到此IP/CNAME" required />
        </div>

        <hr v-if="formData.type <= 2" class="border-border/60" />

        <!-- 监控协议设置 -->
        <div v-if="formData.type <= 2" class="space-y-4">
          <div class="flex items-center gap-6">
            <div class="flex-1">
              <label class="label">检测协议 <span class="text-danger">*</span></label>
              <select v-model="formData.checktype" class="input w-full">
                <option :value="0">PING 监控</option>
                <option :value="1">TCP 端口检测</option>
                <option :value="2">HTTP(S) 状态码监控</option>
              </select>
            </div>
            <div class="flex-1" v-if="formData.checktype === 1">
              <label class="label">TCP检测端口 <span class="text-danger">*</span></label>
              <input type="number" v-model="formData.tcpport" class="input w-full" placeholder="例如: 80" />
            </div>
          </div>
          
          <div v-if="formData.checktype === 2">
            <label class="label">探测地址 (HTTP/HTTPS) <span class="text-danger">*</span></label>
            <input v-model="formData.checkurl" class="input font-mono w-full" placeholder="http:// 或 https:// 起头" />
          </div>
          
          <div v-if="formData.checktype < 2">
            <label class="label">自定义探测IP</label>
            <input v-model="formData.checkurl" class="input font-mono w-full" placeholder="留空则自动检测目标解析记录IP" />
          </div>
        </div>

        <!-- 条件设置 -->
        <div v-if="formData.type === 3">
          <label class="label">恢复阈值 <span class="text-danger">*</span></label>
          <input type="number" v-model="formData.cycle" class="input" placeholder="同域名下正常节点数量 <= X时恢复此节点" required />
        </div>

        <div class="grid grid-cols-3 gap-2" v-if="formData.type <= 2">
          <div>
            <label class="label" title="单位: 秒">容忍超时 <span class="text-danger">*</span></label>
            <div class="relative">
              <input type="number" v-model="formData.timeout" class="input w-full pr-8" min="1" required />
              <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-text-muted text-xs">s</div>
            </div>
          </div>
          <div>
            <label class="label" title="单位: 秒">检测间隔 <span class="text-danger">*</span></label>
            <div class="relative">
              <input type="number" v-model="formData.frequency" class="input w-full pr-8" min="1" required />
              <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-text-muted text-xs">s</div>
            </div>
          </div>
          <div>
            <label class="label" title="需连续失败几次才切换">确认次数 <span class="text-danger">*</span></label>
            <div class="relative">
              <input type="number" v-model="formData.cycle" class="input w-full pr-8" min="1" required />
              <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-text-muted text-xs">次</div>
            </div>
          </div>
        </div>

        <!-- Extra -->
        <div>
          <label class="label">备注标签</label>
          <input v-model="formData.remark" class="input" placeholder="例如: 华南区主节点" />
        </div>

        <div v-if="overview?.run_state === 0" class="p-3 bg-danger/10 border border-danger/30 rounded-lg text-danger text-sm flex gap-2">
          <ShieldAlert class="w-4 h-4 shrink-0 mt-0.5" />
          <span><b>系统警告</b>：目前离线监控定时任务未启动！请在宝塔面板配置定时任务来使其生效。此策略当前不会执行。</span>
        </div>
      </form>
      <template #footer>
        <div class="flex gap-3">
          <button class="btn-outline flex-1" @click="showFormSlide = false">取消</button>
          <button class="btn-primary flex-1" :disabled="saveMutation.isPending.value" @click="saveMutation.mutate(formData)">
            <span v-if="saveMutation.isPending.value" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
            提交保存
          </button>
        </div>
      </template>
    </SlideOver>

    <!-- Logs SlideOver -->
    <SlideOver :open="!!logTarget" @update:open="(v) => { if (!v) logTarget = null }" :title="`切换日志: ${logTarget?.domain}`" size="md">
      <div v-if="logsLoading" class="py-10 text-center text-text-muted">加载日志中...</div>
      <div v-else-if="!logsData?.data?.length" class="py-10 text-center text-text-muted">暂无切换日志</div>
      <div v-else class="space-y-4">
        <div v-for="log in logsData?.data" :key="log.id" class="p-3 bg-bg-mute rounded-lg border border-border text-sm">
          <div class="flex items-center justify-between mb-1">
            <span :class="['font-medium', log.action === 1 ? 'text-danger' : log.action === 2 ? 'text-success' : 'text-info']">
              {{ log.action === 1 ? '触发切换 (宕机)' : log.action === 2 ? '恢复解析 (健康)' : '操作日志' }}
            </span>
            <span class="text-xs text-text-muted">{{ log.date }}</span>
          </div>
          <p class="text-text-muted mt-1 leading-relaxed">{{ log.error || '无详细信息' }}</p>
        </div>

        <!-- Pagination -->
        <div class="flex justify-between items-center pt-4 border-t border-border mt-4" v-if="(logsData?.total ?? 0) > 15">
          <button class="btn-outline btn-sm" :disabled="logPage <= 1" @click="logPage--">上一页</button>
          <span class="text-xs text-text-muted">第 {{ logPage }} 页</span>
          <button class="btn-outline btn-sm" :disabled="logsData?.data?.length < 15" @click="logPage++">下一页</button>
        </div>
      </div>
    </SlideOver>

    <ConfirmDialog
      :open="!!deleteTarget"
      title="删除策略"
      :message="`确定要删除 ${deleteTarget?.domain} 的此容灾策略吗？删除后不再监控。`"
      :loading="deleteMutation.isPending.value"
      @update:open="(v) => { if (!v) deleteTarget = null }"
      @confirm="deleteTarget && deleteMutation.mutate(deleteTarget.id)"
    />
  </div>
</template>
