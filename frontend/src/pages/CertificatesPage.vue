<script setup lang="ts">
import { ref, computed, onUnmounted, watch } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { certificatesApi, deployApi, type Certificate } from '@/api'
import { useToast } from '@/composables/useToast'
import { ShieldCheck, Plus, Trash2, RefreshCw, Clock, Download, Send, FileText, RefreshCcw, XOctagon, Play } from 'lucide-vue-next'
import StatusBadge from '@/components/shared/StatusBadge.vue'
import SkeletonRow from '@/components/shared/SkeletonRow.vue'
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue'
import SlideOver from '@/components/shared/SlideOver.vue'

const toast = useToast()
const qc = useQueryClient()
const deleteTarget = ref<Certificate | null>(null)
const resetTarget = ref<Certificate | null>(null)
const revokeTarget = ref<Certificate | null>(null)
const executeTarget = ref<Certificate | null>(null)
const deployTarget = ref<Certificate | null>(null)
const showAddSlide = ref(false)
const showDownloadSlide = ref(false)
const showDeploySlide = ref(false)
const deployAccountsSelected = ref<number[]>([])

const downloadData = ref<any>(null)
const showLogSlide = ref(false)
const logContent = ref('')
const logProcessId = ref('')
let logInterval: any = null

const addForm = ref({ domain: '', account_id: '' })

const { data: accounts } = useQuery({ queryKey: ['cert-accounts'], queryFn: async () => (await certificatesApi.accountList()).data })
const { data: deployAccounts } = useQuery({ queryKey: ['deploy-accounts'], queryFn: async () => (await deployApi.accountList()).data })

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

const toggleAutoRenewMutation = useMutation({
  mutationFn: (param: { id: number, isauto: number }) => certificatesApi.autoRenew(param.id, param.isauto),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['certificates'] })
    toast.success('自动续签设置已更新')
  },
  onError: () => toast.error('更新自动续签设置失败'),
})

const resetMutation = useMutation({
  mutationFn: (id: number) => certificatesApi.reset(id),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['certificates'] })
    resetTarget.value = null
    toast.success('证书阶段已重置')
  },
  onError: () => toast.error('重置失败'),
})

const revokeMutation = useMutation({
  mutationFn: (id: number) => certificatesApi.revoke(id),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['certificates'] })
    revokeTarget.value = null
    toast.success('证书已吊销')
  },
  onError: () => toast.error('吊销失败'),
})

const executeMutation = useMutation({
  mutationFn: (param: { id: number, reset: number }) => certificatesApi.execute(param.id, { reset: param.reset }),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['certificates'] })
    executeTarget.value = null
    toast.success('签发任务已提交并在后台执行')
  },
  onError: () => toast.error('提交失败'),
})

const deployMutation = useMutation({
  mutationFn: () => certificatesApi.deploy(deployTarget.value!.id, { deploy_accounts: deployAccountsSelected.value }),
  onSuccess: () => {
    showDeploySlide.value = false
    toast.success('部署指令已下发')
    deployAccountsSelected.value = []
  },
  onError: () => toast.error('部署指令下发失败'),
})

async function openDownload(cert: Certificate) {
  try {
    const res = await certificatesApi.detail(cert.id)
    downloadData.value = res.data
    showDownloadSlide.value = true
  } catch (err) {
    toast.error('获取证书详情失败')
  }
}

function copyToClipboard(text: string) {
  navigator.clipboard.writeText(text)
  toast.success('已复到剪贴板')
}

function downloadFile(content: string, filename: string) {
  const blob = new Blob([content], { type: 'text/plain' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  a.click()
  URL.revokeObjectURL(url)
}

function downloadPFX(base64Content: string) {
  const bstr = atob(base64Content)
  let n = bstr.length
  const u8arr = new Uint8Array(n)
  while (n--) {
    u8arr[n] = bstr.charCodeAt(n)
  }
  const blob = new Blob([u8arr], { type: 'application/x-pkcs12' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'cert.pfx'
  a.click()
  URL.revokeObjectURL(url)
}

function openLog(processid: string) {
  logProcessId.value = processid
  logContent.value = '正在加载日志...'
  showLogSlide.value = true
  fetchLog()
  logInterval = setInterval(fetchLog, 1500)
}

async function fetchLog() {
  if (!logProcessId.value || !showLogSlide.value) return
  try {
    const res = await certificatesApi.log(logProcessId.value)
    logContent.value = res.data?.content || '暂无日志'
  } catch (err) {
    // silently fail
  }
}

watch(showLogSlide, (val) => {
  if (!val && logInterval) {
    clearInterval(logInterval)
    logInterval = null
  }
})

onUnmounted(() => {
  if (logInterval) clearInterval(logInterval)
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
            <th>自动续签</th>
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
            <td>
              <button 
                @click="toggleAutoRenewMutation.mutate({ id: cert.id, isauto: cert.isauto ? 0 : 1 })"
                :class="['relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors', cert.isauto ? 'bg-success' : 'bg-border']"
              >
                <span :class="['pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out', cert.isauto ? 'translate-x-4' : 'translate-x-0']" />
              </button>
            </td>
            <td class="hidden md:table-cell text-text-muted text-xs">
              <span v-if="cert.expire_time" class="flex items-center gap-1">
                <Clock class="w-3 h-3" /> {{ cert.expire_time }}
              </span>
              <span v-else>—</span>
            </td>
            <td class="hidden sm:table-cell text-text-muted text-xs">{{ cert.created_at }}</td>
            <td class="text-right">
              <div class="flex justify-end gap-1 opacity-100 transition-opacity">
                <!-- Actions -->
                <button v-if="cert.status !== 3" title="执行申请/续签" class="btn-icon hover:text-success" @click="executeTarget = cert">
                  <Play class="w-3.5 h-3.5" />
                </button>
                <button v-if="cert.status === 3" title="重新申请" class="btn-icon hover:text-success" @click="executeTarget = cert">
                  <Play class="w-3.5 h-3.5" />
                </button>
                <button v-if="cert.status === 3" title="下载证书" class="btn-icon hover:text-primary" @click="openDownload(cert)">
                  <Download class="w-3.5 h-3.5" />
                </button>
                <button v-if="cert.status === 3" title="部署证书" class="btn-icon hover:text-primary" @click="deployTarget = cert; showDeploySlide = true; deployAccountsSelected = []">
                  <Send class="w-3.5 h-3.5" />
                </button>
                <button v-if="cert.processid" title="查看日志" class="btn-icon hover:text-primary" @click="openLog(cert.processid!)">
                  <FileText class="w-3.5 h-3.5" />
                </button>
                <button title="重置状态" class="btn-icon hover:text-warn" @click="resetTarget = cert">
                  <RefreshCcw class="w-3.5 h-3.5" />
                </button>
                <button v-if="cert.status === 3" title="吊销证书" class="btn-icon hover:text-danger" @click="revokeTarget = cert">
                  <XOctagon class="w-3.5 h-3.5" />
                </button>
                <button title="删除证书" class="btn-icon hover:text-danger" @click="deleteTarget = cert">
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
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

    <SlideOver v-model:open="showDeploySlide" title="一键部署证书">
      <div class="space-y-4">
        <p class="text-sm text-text-muted">为 {{ deployTarget?.domain }} 部署证书至以下账户：</p>
        <div class="space-y-2 max-h-96 overflow-y-auto">
          <label v-for="acc in deployAccounts?.data" :key="acc.id" class="flex items-center gap-3 p-3 rounded-lg border border-border hover:bg-bg-dark cursor-pointer transition-colors">
            <input type="checkbox" :value="acc.id" v-model="deployAccountsSelected" class="checkbox">
            <div>
              <div class="font-medium">{{ acc.name || `账户 ${acc.id}` }}</div>
              <div class="text-xs text-text-muted">{{ acc.remark || acc.type }}</div>
            </div>
          </label>
          <div v-if="!deployAccounts?.data?.length" class="text-center py-6 text-sm text-text-muted">暂无部署账户，请前往部署账户页面添加</div>
        </div>
      </div>
      <template #footer>
        <div class="flex gap-3">
          <button class="btn-outline flex-1" @click="showDeploySlide = false">取消</button>
          <button class="btn-primary flex-1" :disabled="!deployAccountsSelected.length || deployMutation.isPending.value" @click="deployMutation.mutate()">
            <span v-if="deployMutation.isPending.value" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
            立即部署
          </button>
        </div>
      </template>
    </SlideOver>

    <SlideOver v-model:open="showDownloadSlide" title="下载证书文件" v-if="downloadData">
      <div class="space-y-6">
        <div>
          <div class="flex justify-between items-end mb-2">
            <label class="label mb-0">证书 (PEM格式, fullchain.crt)</label>
            <div class="flex gap-2">
              <button class="btn-ghost btn-sm" @click="copyToClipboard(downloadData.fullchain)">复制</button>
              <button class="btn-ghost btn-sm" @click="downloadFile(downloadData.fullchain, 'fullchain.crt')">下载</button>
            </div>
          </div>
          <textarea class="input font-mono text-xs h-32" readonly :value="downloadData.fullchain" />
        </div>
        
        <div>
          <div class="flex justify-between items-end mb-2">
            <label class="label mb-0">私钥 (PEM格式, private.key)</label>
            <div class="flex gap-2">
              <button class="btn-ghost btn-sm" @click="copyToClipboard(downloadData.privatekey)">复制</button>
              <button class="btn-ghost btn-sm" @click="downloadFile(downloadData.privatekey, 'private.key')">下载</button>
            </div>
          </div>
          <textarea class="input font-mono text-xs h-32" readonly :value="downloadData.privatekey" />
        </div>

        <div v-if="downloadData.pfx">
          <label class="label">IIS服务器 (PFX格式)</label>
          <p class="text-sm text-text-muted mb-2">默认证书密码为: 123456</p>
          <button class="btn-outline w-full" @click="downloadPFX(downloadData.pfx)">
            <Download class="w-4 h-4 mr-2" /> 下载 PFX 证书
          </button>
        </div>
      </div>
    </SlideOver>

    <SlideOver v-model:open="showLogSlide" title="执行日志">
      <div class="bg-bg-dark rounded-xl p-4 overflow-x-auto h-full max-h-[calc(100vh-120px)] border border-border">
        <pre class="font-mono text-xs leading-relaxed text-text-muted whitespace-pre-wrap">{{ logContent }}</pre>
      </div>
    </SlideOver>

    <ConfirmDialog
      :open="!!deleteTarget"
      title="删除证书"
      :message="`确定删除 ${deleteTarget?.domain} 的证书？删除后不可恢复。`"
      :loading="deleteMutation.isPending.value"
      @update:open="(v) => { if (!v) deleteTarget = null }"
      @confirm="deleteTarget && deleteMutation.mutate(deleteTarget.id)"
    />

    <ConfirmDialog
      :open="!!resetTarget"
      title="重置证书状态"
      :message="`确定重置 ${resetTarget?.domain} 的状态？重置后将回到待提交状态。`"
      :loading="resetMutation.isPending.value"
      @update:open="(v) => { if (!v) resetTarget = null }"
      @confirm="resetTarget && resetMutation.mutate(resetTarget.id)"
    />

    <ConfirmDialog
      :open="!!revokeTarget"
      title="吊销证书"
      :message="`确定吊销 ${revokeTarget?.domain} 的证书？吊销后浏览器将不再信任该证书。`"
      :loading="revokeMutation.isPending.value"
      @update:open="(v) => { if (!v) revokeTarget = null }"
      @confirm="revokeTarget && revokeMutation.mutate(revokeTarget.id)"
    />

    <ConfirmDialog
      :open="!!executeTarget"
      :title="executeTarget?.status === 3 ? '重新申请证书' : '执行证书签发'"
      :message="executeTarget?.status === 3 ? `是否确定重新申请 ${executeTarget?.domain} 的证书？` : `是否确定开始执行 ${executeTarget?.domain} 的证书签发任务？`"
      :loading="executeMutation.isPending.value"
      @update:open="(v) => { if (!v) executeTarget = null }"
      @confirm="executeTarget && executeMutation.mutate({ id: executeTarget.id, reset: executeTarget.status === 3 ? 1 : 0 })"
    />
  </div>
</template>
