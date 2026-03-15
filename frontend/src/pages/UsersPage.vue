<script setup lang="ts">
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { usersApi, domainsApi, type UserItem } from '@/api'
import { useToast } from '@/composables/useToast'
import { ref } from 'vue'
import { Users, RefreshCw, Trash2, ToggleLeft, ToggleRight, Plus, Edit2, Shield, Key } from 'lucide-vue-next'
import StatusBadge from '@/components/shared/StatusBadge.vue'
import SkeletonRow from '@/components/shared/SkeletonRow.vue'
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue'
import SlideOver from '@/components/shared/SlideOver.vue'

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

const { data: domains } = useQuery({
  queryKey: ['domains-list'],
  queryFn: async () => (await domainsApi.list({ limit: 1000 })).data?.data || []
})

const showFormSlide = ref(false)
const isEdit = ref(false)
const loadingDetail = ref(false)

const defaultForm = () => ({
  id: 0,
  username: '',
  password: '',
  repwd: '',
  is_api: 0,
  apikey: '',
  level: 1, // 1=普通 2=管理
  permission: [] as string[]
})

const form = ref(defaultForm())

const saveMutation = useMutation({
  mutationFn: (data: any) => isEdit.value ? usersApi.update(data.id, data) : usersApi.create(data),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['users'] })
    showFormSlide.value = false
    toast.success(isEdit.value ? '用户已更新' : '用户已创建')
  },
  onError: (err: any) => toast.error(err.response?.data?.message || '操作失败')
})

function openAdd() {
  isEdit.value = false
  form.value = defaultForm()
  showFormSlide.value = true
}

async function openEdit(user: UserItem) {
  isEdit.value = true
  form.value = { ...defaultForm(), id: user.id, username: user.username, level: user.level }
  showFormSlide.value = true
  loadingDetail.value = true
  try {
    const res = await usersApi.detail(user.id)
    const detail = res.data?.data
    if (detail) {
      form.value.is_api = detail.is_api
      form.value.apikey = detail.apikey || ''
      form.value.permission = detail.permission || []
    }
  } catch {
    toast.error('获取用户详情失败')
  } finally {
    loadingDetail.value = false
  }
}

function generateApikey() {
  form.value.apikey = Array.from({ length: 32 }, () => Math.floor(Math.random() * 36).toString(36)).join('')
}

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
      <div class="flex gap-2">
        <button class="btn-ghost btn-sm" @click="refetch()"><RefreshCw class="w-3.5 h-3.5" /> 刷新</button>
        <button class="btn-primary btn-sm" @click="openAdd()"><Plus class="w-3.5 h-3.5" /> 新增用户</button>
      </div>
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
                <button class="btn-icon text-primary" @click="openEdit(user)" title="编辑用户"><Edit2 class="w-3.5 h-3.5" /></button>
                <button class="btn-icon hover:text-danger" @click="deleteTarget = user" title="删除用户"><Trash2 class="w-3.5 h-3.5" /></button>
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

    <!-- User Add/Edit Dialog -->
    <SlideOver v-model:open="showFormSlide" :title="isEdit ? '编辑用户' : '新增用户'">
      <div v-if="loadingDetail" class="py-12 flex flex-col items-center justify-center text-text-muted">
        <RefreshCw class="w-6 h-6 animate-spin mb-4" />
        <p>正在读取用户数据...</p>
      </div>
      <form v-else class="space-y-5" @submit.prevent>
        <div>
          <label class="label">用户名 <span class="text-danger">*</span></label>
          <input class="input" v-model="form.username" required placeholder="登录使用的账户名" :disabled="isEdit" />
        </div>
        
        <div>
          <label class="label">{{ isEdit ? '重置密码' : '登录密码 <span class="text-danger">*</span>' }}</label>
          <input v-if="!isEdit" type="password" class="input" v-model="form.password" required placeholder="设置初始密码" />
          <input v-else type="password" class="input" v-model="form.repwd" placeholder="不修改请留空" />
        </div>

        <div>
          <label class="label">账户角色 <span class="text-danger">*</span></label>
          <div class="flex gap-4 mt-2">
            <label class="flex items-center gap-2 cursor-pointer p-3 border border-border rounded-xl flex-1 hover:border-accent/40 bg-bg transition-colors" :class="{ 'border-accent bg-accent/5 ring-1 ring-accent text-accent': form.level === 1 }">
              <input type="radio" v-model="form.level" :value="1" class="hidden" />
              <Users class="w-5 h-5 shrink-0" />
              <div>
                <div class="font-medium text-sm">普通用户</div>
                <div class="text-xs text-text-muted mt-0.5">只能管理被授权的域名</div>
              </div>
            </label>
            <label class="flex items-center gap-2 cursor-pointer p-3 border border-border rounded-xl flex-1 hover:border-accent/40 bg-bg transition-colors" :class="{ 'border-accent bg-accent/5 ring-1 ring-accent text-accent': form.level === 2 }">
              <input type="radio" v-model="form.level" :value="2" class="hidden" />
              <Shield class="w-5 h-5 shrink-0" />
              <div>
                <div class="font-medium text-sm">系统管理员</div>
                <div class="text-xs text-text-muted mt-0.5">拥有所有管理权限</div>
              </div>
            </label>
          </div>
        </div>

        <div v-if="form.level === 1" class="pt-2 border-t border-border/60">
          <label class="label">域名授权</label>
          <p class="text-xs text-text-muted mb-3">勾选此用户可以管理的域名列表，留空表示没有任何域名管理权限</p>
          <div class="max-h-64 overflow-y-auto border border-border rounded-xl p-2 bg-bg space-y-1">
            <label v-for="domain in domains" :key="domain.id" class="flex items-center gap-2.5 p-2 rounded-lg hover:bg-bg-mute cursor-pointer transition-colors">
              <input type="checkbox" :value="domain.name" v-model="form.permission" class="checkbox rounded text-accent" />
              <span class="text-sm">{{ domain.name }}</span>
            </label>
            <div v-if="domains?.length === 0" class="p-4 text-center text-sm text-text-muted">当前系统内无域名</div>
          </div>
        </div>

        <div class="pt-2 border-t border-border/60 space-y-4">
          <div class="flex items-center justify-between">
            <div>
              <label class="label">API 访问权限</label>
              <p class="text-xs text-text-muted">允许该用户通过 API 编程方式操作</p>
            </div>
            <button
              type="button"
              @click="form.is_api = form.is_api === 1 ? 0 : 1"
              :class="['relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full border-2 border-transparent transition-colors', form.is_api === 1 ? 'bg-success' : 'bg-border']"
            >
              <span :class="['pointer-events-none inline-block h-4 w-4 transform rounded-full bg-bg shadow ring-0 transition duration-200 ease-in-out', form.is_api === 1 ? 'translate-x-4' : 'translate-x-0']" />
            </button>
          </div>

          <div v-if="form.is_api === 1" class="animate-in fade-in slide-in-from-top-2">
            <label class="label">API Key <span class="text-danger">*</span></label>
            <div class="flex gap-2">
              <div class="relative flex-1">
                <Key class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-text-muted" />
                <input class="input pl-9 font-mono text-sm" v-model="form.apikey" required placeholder="请生成或输入 32 位随机密钥" />
              </div>
              <button class="btn-outline shrink-0" @click="generateApikey">重新生成</button>
            </div>
          </div>
        </div>
      </form>
      <template #footer>
        <div class="flex gap-3">
          <button class="btn-outline flex-1" @click="showFormSlide = false">取消</button>
          <button class="btn-primary flex-1" :disabled="saveMutation.isPending.value || loadingDetail" @click="saveMutation.mutate(form)">
            <span v-if="saveMutation.isPending.value" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
            {{ isEdit ? '保存修改' : '创建用户' }}
          </button>
        </div>
      </template>
    </SlideOver>
  </div>
</template>
