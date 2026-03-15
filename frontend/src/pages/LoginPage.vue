<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { Zap, Eye, EyeOff } from 'lucide-vue-next'

const router = useRouter()
const auth = useAuthStore()

const form = reactive({ username: '', password: '' })
const showPassword = ref(false)
const error = ref('')

async function handleLogin() {
  error.value = ''
  const result = await auth.login(form.username, form.password)
  if (result.success) {
    router.push('/')
  } else {
    error.value = result.message || '登录失败'
  }
}
</script>

<template>
  <div class="min-h-screen bg-bg flex items-center justify-center px-4">
    <!-- Background grid -->
    <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJub25lIiBmaWxsLXJ1bGU9ImV2ZW5vZGQiPjxnIGZpbGw9IiMyNzI3MmEiIGZpbGwtb3BhY2l0eT0iMC40Ij48cGF0aCBkPSJNMzYgMzRoLTJ2LTJoMnYyem0wLTloLTJ2LTJoMnYyem0tOSA5aC0ydi0yaDJ2MnptMC05aC0ydi0yaDJ2MnoiLz48L2c+PC9nPjwvc3ZnPg==')] opacity-40 pointer-events-none" />

    <div class="relative w-full max-w-sm">
      <!-- Logo mark -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-14 h-14 bg-accent rounded-2xl mb-4 shadow-glow-accent">
          <Zap class="w-7 h-7 text-white" />
        </div>
        <h1 class="text-2xl font-bold text-text">聚合 DNS 管理</h1>
        <p class="text-sm text-text-muted mt-1">登录以继续访问控制台</p>
      </div>

      <!-- Card -->
      <div class="card border-border/80 shadow-panel">
        <form @submit.prevent="handleLogin" class="space-y-4">
          <div>
            <label class="label">用户名</label>
            <input
              v-model="form.username"
              type="text"
              class="input"
              placeholder="请输入用户名"
              autocomplete="username"
              required
            />
          </div>
          <div>
            <label class="label">密码</label>
            <div class="relative">
              <input
                v-model="form.password"
                :type="showPassword ? 'text' : 'password'"
                class="input pr-10"
                placeholder="请输入密码"
                autocomplete="current-password"
                required
              />
              <button
                type="button"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-text-disabled hover:text-text-muted transition-colors"
                @click="showPassword = !showPassword"
              >
                <Eye v-if="!showPassword" class="w-4 h-4" />
                <EyeOff v-else class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Error -->
          <Transition name="fade">
            <p v-if="error" class="text-xs text-danger bg-danger/10 border border-danger/20 rounded-lg px-3 py-2.5">
              {{ error }}
            </p>
          </Transition>

          <button
            type="submit"
            class="btn-primary w-full justify-center mt-2"
            :disabled="auth.isLoading"
          >
            <span
              v-if="auth.isLoading"
              class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"
            />
            {{ auth.isLoading ? '登录中...' : '登录' }}
          </button>
        </form>
      </div>
      <p class="text-center text-xs text-text-disabled mt-5">DNSMgr · 聚合 DNS 管理系统</p>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
