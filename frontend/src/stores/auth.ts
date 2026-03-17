import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi, type User } from '@/api'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem('dns_token'))
  const userStr = localStorage.getItem('dns_user')
  const user = ref<User | null>(userStr && userStr !== 'undefined' ? JSON.parse(userStr) : null)
  const isLoading = ref(false)

  const isAuthenticated = computed(() => !!token.value)
  const isAdmin = computed(() => user.value?.level === 2)

  async function login(username: string, password: string) {
    isLoading.value = true
    try {
      const { data } = await authApi.login(username, password)
      if (data.success && data.data?.token && data.data?.user) {
        token.value = data.data.token
        user.value = data.data.user
        localStorage.setItem('dns_token', data.data.token)
        localStorage.setItem('dns_user', JSON.stringify(data.data.user))
        return { success: true }
      }
      return { success: false, message: data.message }
    } catch (err: unknown) {
      const msg = (err as { response?: { data?: { message?: string } } })?.response?.data?.message
        || '登录失败，请检查用户名和密码'
      return { success: false, message: msg }
    } finally {
      isLoading.value = false
    }
  }

  async function fetchProfile() {
    try {
      const { data } = await authApi.profile()
      if (data.success) {
        user.value = data.data
        localStorage.setItem('dns_user', JSON.stringify(data.data))
      }
    } catch {
      // ignore
    }
  }

  function logout() {
    token.value = null
    user.value = null
    localStorage.removeItem('dns_token')
    localStorage.removeItem('dns_user')
  }

  async function exchangeToken() {
    try {
      const { data } = await authApi.exchangeToken()
      if (data.success) {
        token.value = data.data.token
        user.value = data.data.user
        localStorage.setItem('dns_token', data.data.token)
        localStorage.setItem('dns_user', JSON.stringify(data.data.user))
        return true
      }
      return false
    } catch {
      return false
    }
  }

  return { token, user, isLoading, isAuthenticated, isAdmin, login, logout, fetchProfile, exchangeToken }
})
