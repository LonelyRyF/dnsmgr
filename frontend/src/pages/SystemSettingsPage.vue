<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useQuery, useMutation } from '@tanstack/vue-query'
import { systemApi } from '@/api'
import { useToast } from '@/composables/useToast'
import { Settings, Save, Mail, Webhook, Link } from 'lucide-vue-next'

const toast = useToast()
const activeTab = ref('base')

const { data: config, isLoading, refetch } = useQuery({
  queryKey: ['system-config'],
  queryFn: async () => (await systemApi.getConfig()).data.data,
})

const saveMutation = useMutation({
  mutationFn: (data: Record<string, unknown>) => systemApi.updateConfig(data),
  onSuccess: () => {
    toast.success('配置已保存')
    refetch()
  },
  onError: () => toast.error('保存失败'),
})

function saveConfig() {
  if (config.value) saveMutation.mutate(config.value as Record<string, unknown>)
}
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
          :class="activeTab === 'proxy' ? 'bg-accent/10 text-accent' : 'text-text-muted hover:bg-bg-hover hover:text-text'"
          @click="activeTab = 'proxy'"
        ><Link class="w-4 h-4" /> 代理设置</button>
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
          <div><label class="label">SMTP 端口</label><input v-model.number="config.mail_port" class="input" type="number" /></div>
          <div><label class="label">密码/授权码</label><input v-model="config.mail_pwd" class="input" type="password" /></div>
        </form>

        <form @submit.prevent="saveConfig" class="space-y-5" v-if="activeTab === 'webhook'">
          <div>
            <label class="label">Webhook URL</label>
            <input v-model="config.webhook_url" class="input" placeholder="https://" />
            <p class="text-xs text-text-muted mt-1.5">配置触发器以在关键操作时接收外部回调</p>
          </div>
        </form>

        <form @submit.prevent="saveConfig" class="space-y-5" v-if="activeTab === 'proxy'">
          <div>
            <label class="label">代理服务器</label>
            <input v-model="config.proxy_server" class="input" placeholder="http://127.0.0.1:7890" />
            <p class="text-xs text-text-muted mt-1.5">留空则不使用代理访问外部接口</p>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
