<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useQuery, useMutation } from '@tanstack/vue-query'
import { systemApi, type SystemInfo } from '@/api'
import { useToast } from '@/composables/useToast'
import { Settings, Save, Mail, Webhook, Link, MessageSquare, Clock, Send, Info, Trash2, ShieldCheck, Database, Cpu } from 'lucide-vue-next'

const toast = useToast()
const activeTab = ref('base')

const { data: config, isLoading, refetch } = useQuery({
  queryKey: ['system-config'],
  queryFn: async () => (await systemApi.getConfig()).data.data,
})

const { data: sysDetailedInfo, isLoading: sysInfoLoading } = useQuery({
  queryKey: ['system-detailed-info'],
  queryFn: async () => (await systemApi.getInfo()).data.data,
  enabled: computed(() => activeTab.value === 'about')
})

const saveMutation = useMutation({
  mutationFn: (data: Record<string, unknown>) => systemApi.updateConfig(data),
  onSuccess: () => {
    toast.success('配置已保存')
    refetch()
    cronRefetch()
  },
  onError: () => toast.error('保存失败'),
})

const { data: cronConfig, refetch: cronRefetch } = useQuery({
  queryKey: ['system-cron-config'],
  queryFn: async () => (await systemApi.getCronConfig()).data?.data,
})

function saveConfig() {
  if (config.value) saveMutation.mutate(config.value as Record<string, unknown>)
}

const testActionMutation = useMutation({
  mutationFn: async ({ type, payload }: { type: string, payload?: any }) => {
    if (type === 'mail') return (await systemApi.testMail()).data
    if (type === 'telegram') return (await systemApi.testTelegram()).data
    if (type === 'webhook') return (await systemApi.testWebhook()).data
    if (type === 'proxy') return (await systemApi.testProxy(payload)).data
  },
  onSuccess: (data: any) => toast.success(data?.message || '测试成功'),
  onError: (err: any) => toast.error(err.response?.data?.message || '测试失败'),
})

function testConfig(type: string) {
  if (type === 'proxy' && config.value) {
    testActionMutation.mutate({ type, payload: {
      proxy_server: config.value.proxy_server,
      proxy_port: config.value.proxy_port,
      proxy_user: config.value.proxy_user,
      proxy_pwd: config.value.proxy_pwd,
      proxy_type: config.value.proxy_type,
    }})
  } else {
    testActionMutation.mutate({ type })
  }
}

function getDomainFromUrl(url: string | undefined): string {
  if (!url) return ''
  try {
    return new URL(url).hostname
  } catch {
    return ''
  }
}

const clearCacheMutation = useMutation({
  mutationFn: () => systemApi.clearCache(),
  onSuccess: () => toast.success('系统缓存已清理'),
  onError: () => toast.error('清理缓存失败'),
})
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title flex items-center gap-2.5">
          <Settings class="w-5 h-5 text-accent" /> 系统设置
        </h1>
        <p class="page-subtitle">修改系统各项核心参数及通知配置</p>
      </div>
      <button class="btn-primary btn-sm" :disabled="saveMutation.isPending.value" @click="saveConfig">
        <span v-if="saveMutation.isPending.value" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
        <Save v-else class="w-3.5 h-3.5" />
        保存设置
      </button>
    </div>

    <div v-if="isLoading" class="flex gap-4">
      <div class="w-48 skeleton h-64 shrink-0 rounded-xl" />
      <div class="flex-1 skeleton h-96 rounded-xl" />
    </div>

    <div v-else-if="config" class="flex flex-col md:flex-row gap-6">
      <!-- Sidebar Nav -->
      <nav class="flex flex-row md:flex-col gap-1 w-full md:w-48 overflow-x-auto shrink-0 border-b md:border-b-0 border-border pb-2 md:pb-0">
        <button
          class="px-4 py-2.5 text-sm font-medium rounded-lg text-left whitespace-nowrap transition-colors"
          :class="activeTab === 'base' ? 'bg-accent/10 text-accent' : 'text-text-muted hover:bg-bg-hover hover:text-text'"
          @click="activeTab = 'base'"
        >基础设置</button>
        <button
          class="px-4 py-2.5 text-sm font-medium rounded-lg text-left whitespace-nowrap flex items-center gap-2 transition-colors"
          :class="activeTab === 'mail' ? 'bg-accent/10 text-accent' : 'text-text-muted hover:bg-bg-hover hover:text-text'"
          @click="activeTab = 'mail'"
        ><Mail class="w-4 h-4" /> 邮件通知</button>
        <button
          class="px-4 py-2.5 text-sm font-medium rounded-lg text-left whitespace-nowrap flex items-center gap-2 transition-colors"
          :class="activeTab === 'webhook' ? 'bg-accent/10 text-accent' : 'text-text-muted hover:bg-bg-hover hover:text-text'"
          @click="activeTab = 'webhook'"
        ><Webhook class="w-4 h-4" /> Webhook</button>
        <button
          class="px-4 py-2.5 text-sm font-medium rounded-lg text-left whitespace-nowrap flex items-center gap-2 transition-colors"
          :class="activeTab === 'telegram' ? 'bg-accent/10 text-accent' : 'text-text-muted hover:bg-bg-hover hover:text-text'"
          @click="activeTab = 'telegram'"
        ><MessageSquare class="w-4 h-4" /> Telegram</button>
        <button
          class="px-4 py-2.5 text-sm font-medium rounded-lg text-left whitespace-nowrap flex items-center gap-2 transition-colors"
          :class="activeTab === 'proxy' ? 'bg-accent/10 text-accent' : 'text-text-muted hover:bg-bg-hover hover:text-text'"
          @click="activeTab = 'proxy'"
        ><Link class="w-4 h-4" /> 代理设置</button>
        <button
          class="px-4 py-2.5 text-sm font-medium rounded-lg text-left whitespace-nowrap flex items-center gap-2 transition-colors"
          :class="activeTab === 'cron' ? 'bg-accent/10 text-accent' : 'text-text-muted hover:bg-bg-hover hover:text-text'"
          @click="activeTab = 'cron'"
        ><Clock class="w-4 h-4" /> 定时任务</button>
        <button
          class="px-4 py-2.5 text-sm font-medium rounded-lg text-left whitespace-nowrap flex items-center gap-2 transition-colors"
          :class="activeTab === 'about' ? 'bg-accent/10 text-accent' : 'text-text-muted hover:bg-bg-hover hover:text-text'"
          @click="activeTab = 'about'"
        ><Info class="w-4 h-4" /> 关于系统</button>
      </nav>

      <!-- Panel Area -->
      <div class="flex-1 max-w-2xl bg-bg-subtle border border-border rounded-xl p-6">
        <form @submit.prevent="saveConfig" class="space-y-5" v-if="activeTab === 'base'">
          <div><label class="label">本站网址</label><input v-model="config.sys_url" class="input" placeholder="http://..." /></div>
          <div><label class="label">建站日期</label><input v-model="config.sys_date" class="input" type="date" /></div>
          <div><label class="label">系统日志清理前几天</label><input v-model.number="config.sys_log_days" class="input" type="number" /></div>
        </form>

        <form @submit.prevent="saveConfig" class="space-y-5" v-if="activeTab === 'mail'">
          <div><label class="label">发信邮箱</label><input v-model="config.mail_name" class="input" /></div>
          <div><label class="label">SMTP 主机</label><input v-model="config.mail_smtp" class="input" /></div>
          <div class="grid grid-cols-2 gap-4">
            <div><label class="label">SMTP 端口</label><input v-model.number="config.mail_port" class="input" type="number" /></div>
            <div><label class="label">密码/授权码</label><input v-model="config.mail_pwd" class="input" type="password" /></div>
          </div>
          <div>
            <button type="button" class="btn-outline w-full mt-2" @click="testConfig('mail')" :disabled="testActionMutation.isPending.value && testActionMutation.variables.value?.type === 'mail'">
              <Send class="w-4 h-4 mr-1.5" /> 发送测试邮件
            </button>
          </div>
        </form>

        <form @submit.prevent="saveConfig" class="space-y-5" v-if="activeTab === 'webhook'">
          <div>
            <label class="label">Webhook URL</label>
            <input v-model="config.webhook_url" class="input" placeholder="https://" />
            <p class="text-xs text-text-muted mt-1.5">配置触发器以在关键操作时接收外部回调</p>
          </div>
          <div>
            <button type="button" class="btn-outline w-full mt-2" @click="testConfig('webhook')" :disabled="testActionMutation.isPending.value && testActionMutation.variables.value?.type === 'webhook'">
              <Send class="w-4 h-4 mr-1.5" /> 发送测试指令
            </button>
          </div>
        </form>

        <form @submit.prevent="saveConfig" class="space-y-5" v-if="activeTab === 'telegram'">
          <div>
            <label class="label">Bot Token</label>
            <input v-model="config.tgbot_token" class="input font-mono" placeholder="123456789:ABCDEFGHIJKLMNOPQRSTUVWXYZ" />
          </div>
          <div>
            <label class="label">Chat ID</label>
            <input v-model="config.tgbot_chatid" class="input font-mono" placeholder="-1001234567890" />
            <p class="text-xs text-text-muted mt-1.5">接收通知的频道或群组/个人 ID</p>
          </div>
          <div>
            <button type="button" class="btn-outline w-full mt-2" @click="testConfig('telegram')" :disabled="testActionMutation.isPending.value && testActionMutation.variables.value?.type === 'telegram'">
              <Send class="w-4 h-4 mr-1.5" /> 发送测试消息
            </button>
          </div>
        </form>

        <form @submit.prevent="saveConfig" class="space-y-5" v-if="activeTab === 'proxy'">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="label">代理类型</label>
              <select v-model="config.proxy_type" class="input">
                <option value="http">HTTP</option>
                <option value="socks5">Socks5</option>
              </select>
            </div>
            <div>
              <label class="label">代理服务器</label>
              <input v-model="config.proxy_server" class="input font-mono" placeholder="127.0.0.1" />
            </div>
          </div>
          
          <div class="grid grid-cols-[1fr_2fr_2fr] gap-4">
            <div><label class="label">端口</label><input v-model.number="config.proxy_port" class="input font-mono" type="number" placeholder="7890" /></div>
            <div><label class="label">认证用户</label><input v-model="config.proxy_user" class="input" placeholder="留空为无鉴权" /></div>
            <div><label class="label">认证密码</label><input v-model="config.proxy_pwd" class="input" type="password" /></div>
          </div>

          <p class="text-xs text-text-muted mt-1.5">仅用于访问外部接口 (如 ACME 申请证书, 获取节点 IP)。本地系统不生效。</p>
          
          <div>
            <button type="button" class="btn-outline w-full mt-2" @click="testConfig('proxy')" :disabled="testActionMutation.isPending.value && testActionMutation.variables.value?.type === 'proxy'">
              <RefreshCw v-if="testActionMutation.isPending.value && testActionMutation.variables.value?.type === 'proxy'" class="w-4 h-4 mr-1.5 animate-spin" />
              <Link v-else class="w-4 h-4 mr-1.5" /> 测试代理连通性
            </button>
          </div>
        </form>

        <div class="space-y-5" v-if="activeTab === 'cron'">
          <div>
            <label class="label">定时任务执行方式</label>
            <select v-model="config.cron_type" class="input">
              <option value="0">Cli 命令行执行</option>
              <option value="1">URL 地址触发</option>
            </select>
            <p class="text-xs text-text-muted mt-1.5">建议使用命令方式执行，确保执行权限为 www</p>
          </div>

          <div v-if="config.cron_type === '0'" class="p-4 bg-bg-mute rounded-lg border border-border">
            <label class="label mb-2">在宝塔面板添加计划任务 (Shell脚本)</label>
            <textarea class="input font-mono text-sm leading-relaxed text-text h-20" readonly>cd {{ cronConfig?.siteurl ? '/www/wwwroot/' + getDomainFromUrl(cronConfig.siteurl) : '网站根目录' }}
sudo -u www php think run:monitor</textarea>
            <p v-if="cronConfig?.is_user_www" class="text-xs text-success mt-2 font-medium">✔️ 当前运行用户已是 www，可以直接使用 php think run:monitor</p>
          </div>

          <div v-if="config.cron_type === '1'" class="p-4 bg-bg-mute rounded-lg border border-border">
            <label class="label mb-2">访问URL触发 (适用于面板定时访问URL服务, 如 1分钟请求1次)</label>
            <div class="flex gap-2">
              <input class="input font-mono text-xs flex-1" readonly :value="`${cronConfig?.siteurl}/api/v1/system/cron?key=${cronConfig?.cron_key}`" />
            </div>
          </div>
          
          
          <button class="btn-primary w-full max-w-[150px] mt-4" :disabled="saveMutation.isPending.value" @click="saveConfig">应用配置</button>
        </div>

        <div class="space-y-6" v-if="activeTab === 'about'">
          <div v-if="sysInfoLoading" class="py-10 text-center"><RefreshCw class="w-6 h-6 animate-spin mx-auto text-text-disabled" /></div>
          <div v-else-if="sysDetailedInfo" class="space-y-6">
            <div class="grid grid-cols-2 gap-4">
              <div class="p-4 bg-bg rounded-xl border border-border">
                <div class="flex items-center gap-2 text-text-muted mb-2"><Cpu class="w-4 h-4" /><span class="text-xs font-medium uppercase tracking-wider">运行环境</span></div>
                <div class="space-y-2 text-sm">
                  <div class="flex justify-between"><span>PHP 版本</span><span class="font-mono text-text">{{ sysDetailedInfo.php_version }}</span></div>
                  <div class="flex justify-between"><span>ThinkPHP</span><span class="font-mono text-text">v{{ sysDetailedInfo.think_version }}</span></div>
                  <div class="flex justify-between"><span>操作系统</span><span class="text-text">{{ sysDetailedInfo.os }}</span></div>
                </div>
              </div>
              <div class="p-4 bg-bg rounded-xl border border-border">
                <div class="flex items-center gap-2 text-text-muted mb-2"><Database class="w-4 h-4" /><span class="text-xs font-medium uppercase tracking-wider">数据库 & 资源</span></div>
                <div class="space-y-2 text-sm">
                  <div class="flex justify-between"><span>数据库版本</span><span class="font-mono text-text">{{ sysDetailedInfo.db_version }}</span></div>
                  <div class="flex justify-between"><span>内存限制</span><span class="text-text">{{ sysDetailedInfo.memory_limit }}</span></div>
                  <div class="flex justify-between"><span>文件限制</span><span class="text-text">{{ sysDetailedInfo.upload_max_filesize }} / {{ sysDetailedInfo.post_max_size }}</span></div>
                </div>
              </div>
            </div>

            <div class="p-4 bg-bg-mute rounded-xl border border-border">
              <div class="flex items-center gap-2 text-text-muted mb-3"><Settings class="w-4 h-4" /><span class="text-xs font-medium uppercase tracking-wider">系统维护</span></div>
              <div class="flex gap-4">
                <button class="btn-outline text-danger border-danger/20 hover:bg-danger/10" @click="clearCacheMutation.mutate()" :disabled="clearCacheMutation.isPending.value">
                  <Trash2 class="w-4 h-4 mr-2" /> 刷新系统缓存
                </button>
              </div>
              <p class="text-[11px] text-text-muted mt-3">定期清理缓存可以解决配置未生效或统计数据滞后的问题。</p>
            </div>

            <div class="flex items-center justify-center pt-4 opacity-40">
              <div class="flex flex-col items-center">
                <ShieldCheck class="w-8 h-8 mb-2" />
                <div class="text-[10px] font-bold tracking-widest uppercase">DNSMgr Console v1.0</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
