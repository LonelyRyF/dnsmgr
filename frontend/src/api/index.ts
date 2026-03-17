import axios from 'axios'
import type { AxiosResponse } from 'axios'
import { useAuthStore } from '@/stores/auth'
import router from '@/router'

export interface ApiResponse<T = unknown> {
  success: boolean
  message: string
  data: T
  timestamp?: number
  errors?: unknown
  meta?: {
    total?: number
    page?: number
    limit?: number
  }
}

export interface PaginatedResponse<T> {
  items: T[]
  pagination: {
    total: number
    page: number
    pageSize: number
    totalPages: number
  }
}

const apiClient = axios.create({
  baseURL: '/api/v1',
  timeout: 30_000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

// Request interceptor: attach JWT
apiClient.interceptors.request.use((config) => {
  const auth = useAuthStore()
  if (auth.token) {
    config.headers.Authorization = `Bearer ${auth.token}`
  }
  return config
})

// Response interceptor: unwrap data, handle 401
apiClient.interceptors.response.use(
  (response: AxiosResponse<ApiResponse>) => response,
  async (error) => {
    if (error.response?.status === 401) {
      const auth = useAuthStore()
      auth.logout()
      router.push('/login')
    }
    return Promise.reject(error)
  }
)

// ─── Auth ─────────────────────────────────────────────────────────────────────
export const authApi = {
  login: (username: string, password: string) =>
    apiClient.post<ApiResponse<{ token: string; user: User }>>('/auth/login', { username, password }),
  logout: () => apiClient.post('/auth/logout'),
  profile: () => apiClient.get<ApiResponse<User>>('/auth/profile'),
  exchangeToken: () =>
    axios.get<ApiResponse<{ token: string; user: User }>>('/api/auth/exchange-token'),
}

// ─── System ───────────────────────────────────────────────────────────────────
export const systemApi = {
  getInfo: () => apiClient.get<ApiResponse<SystemInfo>>('/system/info'),
  getConfig: () => apiClient.get<ApiResponse<SystemConfig>>('/system/config'),
  updateConfig: (data: Partial<SystemConfig>) => apiClient.post('/system/config', data),
  clearCache: () => apiClient.post('/system/clear-cache'),
  testMail: (data?: object) => apiClient.post('/system/test-mail', data),
  testTelegram: (data?: object) => apiClient.post('/system/test-telegram', data),
  testWebhook: (data?: object) => apiClient.post('/system/test-webhook', data),
  testProxy: (data?: object) => apiClient.post('/system/test-proxy', data),
  getCronConfig: () => apiClient.get('/system/cron-config'),
}

// ─── Accounts ─────────────────────────────────────────────────────────────────
export const accountsApi = {
  list: (params?: object) => apiClient.get<ApiResponse<DnsAccount[]>>('/accounts/list', { params }),
  create: (data: object) => apiClient.post('/accounts/create', data),
  detail: (id: number) => apiClient.get<ApiResponse<DnsAccount>>(`/accounts/${id}/detail`),
  update: (id: number, data: object) => apiClient.post(`/accounts/${id}/update`, data),
  delete: (id: number) => apiClient.post(`/accounts/${id}/delete`),
}

// ─── Domains ──────────────────────────────────────────────────────────────────
export const domainsApi = {
  list: (params?: object) => apiClient.get<ApiResponse<DomainItem[]>>('/domains/list', { params }),
  create: (data: object) => apiClient.post('/domains/create', data),
  detail: (id: number) => apiClient.get<ApiResponse<DomainItem>>(`/domains/${id}/detail`),
  update: (id: number, data: object) => apiClient.post(`/domains/${id}/update`, data),
  delete: (id: number) => apiClient.post(`/domains/${id}/delete`),
  sync: (id: number) => apiClient.post(`/domains/${id}/sync`),
  batchOperation: (data: object) => apiClient.post('/domains/batch-operation', data),
}

// ─── Records ──────────────────────────────────────────────────────────────────
export const recordsApi = {
  list: (domainId: number, params?: object) =>
    apiClient.get<ApiResponse<DnsRecord[]>>(`/records/${domainId}/list`, { params }),
  detail: (domainId: number, id: number) =>
    apiClient.get<ApiResponse<DnsRecord>>(`/records/${domainId}/${id}/detail`),
  create: (domainId: number, data: object) =>
    apiClient.post(`/records/${domainId}/create`, data),
  update: (domainId: number, id: number, data: object) =>
    apiClient.post(`/records/${domainId}/${id}/update`, data),
  delete: (domainId: number, id: number) =>
    apiClient.post(`/records/${domainId}/${id}/delete`),
  toggleStatus: (domainId: number, id: number) =>
    apiClient.post(`/records/${domainId}/${id}/status`),
  batchCreate: (domainId: number, data: object) =>
    apiClient.post(`/records/${domainId}/batch-create`, data),
  batchOperation: (domainId: number, data: object) =>
    apiClient.post(`/records/${domainId}/batch-operation`, data),
  batchUpdate: (domainId: number, data: object) =>
    apiClient.post(`/records/${domainId}/batch-update`, data),
}

// ─── Certificates ─────────────────────────────────────────────────────────────
export const certificatesApi = {
  accountList: () => apiClient.get('/cert-accounts/list'),
  accountCreate: (data: object) => apiClient.post('/cert-accounts/create', data),
  accountUpdate: (id: number, data: object) => apiClient.post(`/cert-accounts/${id}/update`, data),
  accountDelete: (id: number) => apiClient.post(`/cert-accounts/${id}/delete`),

  list: (params?: object) => apiClient.get<ApiResponse<Certificate[]>>('/certificates/list', { params }),
  create: (data: object) => apiClient.post('/certificates/create', data),
  detail: (id: number) => apiClient.get(`/certificates/${id}/detail`),
  delete: (id: number) => apiClient.post(`/certificates/${id}/delete`),
  deploy: (id: number, data: object) => apiClient.post(`/certificates/${id}/deploy`, data),
  process: (id: number) => apiClient.get(`/certificates/${id}/process`),
  log: (processid: string) => apiClient.get('/certificates/log', { params: { processid } }),
  autoRenew: (id: number, isauto: number) => apiClient.post(`/certificates/${id}/auto-renew`, { isauto }),
  reset: (id: number) => apiClient.post(`/certificates/${id}/reset`),
  execute: (id: number, data?: object) => apiClient.post(`/certificates/${id}/execute`, data),
  revoke: (id: number) => apiClient.post(`/certificates/${id}/revoke`),
}

// ─── Deploy Tasks ─────────────────────────────────────────────────────────────
export const deployApi = {
  accountList: () => apiClient.get('/deploy-accounts/list'),
  accountCreate: (data: object) => apiClient.post('/deploy-accounts/create', data),
  accountUpdate: (id: number, data: object) => apiClient.post(`/deploy-accounts/${id}/update`, data),
  accountDelete: (id: number) => apiClient.post(`/deploy-accounts/${id}/delete`),

  taskList: (params?: object) => apiClient.get<ApiResponse<DeployTask[]>>('/deploy-tasks/list', { params }),
  taskCreate: (data: object) => apiClient.post('/deploy-tasks/create', data),
  taskUpdate: (id: number, data: object) => apiClient.post(`/deploy-tasks/${id}/update`, data),
  taskDelete: (id: number) => apiClient.post(`/deploy-tasks/${id}/delete`),
  taskToggleActive: (id: number) => apiClient.post(`/deploy-tasks/${id}/active`),
  taskReset: (id: number) => apiClient.post(`/deploy-tasks/${id}/reset`),
  taskExecute: (id: number) => apiClient.post(`/deploy-tasks/${id}/execute`),
  taskProcess: (id: number) => apiClient.get(`/deploy-tasks/${id}/process`),
  taskLog: (processid: string) => apiClient.get('/deploy-tasks/log', { params: { processid } }),
}

// ─── Monitor ──────────────────────────────────────────────────────────────────
export const monitorApi = {
  overview: () => apiClient.get<ApiResponse<MonitorOverview>>('/monitor/overview'),
  status: () => apiClient.get('/monitor/status'),
  cleanLogs: () => apiClient.post('/monitor/logs/clean'),
  taskList: (params?: object) => apiClient.get<ApiResponse<MonitorTask[]>>('/monitor/tasks/list', { params }),
  taskDetail: (id: number) => apiClient.get(`/monitor/tasks/${id}/detail`),
  taskCreate: (data: object) => apiClient.post('/monitor/tasks/create', data),
  taskUpdate: (id: number, data: object) => apiClient.post(`/monitor/tasks/${id}/update`, data),
  taskDelete: (id: number) => apiClient.post(`/monitor/tasks/${id}/delete`),
  taskToggleActive: (id: number) => apiClient.post(`/monitor/tasks/${id}/active`),
  taskBatchOperation: (data: object) => apiClient.post('/monitor/tasks/batch-operation', data),
  taskLogs: (id: number, params?: object) => apiClient.get(`/monitor/tasks/${id}/logs`, { params }),
}

// ─── Users ────────────────────────────────────────────────────────────────────
export const usersApi = {
  list: (params?: object) => apiClient.get<ApiResponse<UserItem[]>>('/users/list', { params }),
  create: (data: object) => apiClient.post('/users/create', data),
  detail: (id: number) => apiClient.get(`/users/${id}/detail`),
  update: (id: number, data: object) => apiClient.post(`/users/${id}/update`, data),
  delete: (id: number) => apiClient.post(`/users/${id}/delete`),
  toggleStatus: (id: number) => apiClient.post(`/users/${id}/status`),
  changePassword: (data: object) => apiClient.post('/users/change-password', data),
}

// ─── Logs ─────────────────────────────────────────────────────────────────────
export const logsApi = {
  list: (params?: object) => apiClient.get<ApiResponse<LogItem[]>>('/logs/list', { params }),
}

// ─── Schedule Tasks ───────────────────────────────────────────────────────────
export const scheduleApi = {
  list: (params?: object) => apiClient.get('/schedule-tasks/list', { params }),
  create: (data: object) => apiClient.post('/schedule-tasks/create', data),
  update: (id: number, data: object) => apiClient.post(`/schedule-tasks/${id}/update`, data),
  delete: (id: number) => apiClient.post(`/schedule-tasks/${id}/delete`),
  toggleActive: (id: number) => apiClient.post(`/schedule-tasks/${id}/active`),
}

// ─── Type Definitions ─────────────────────────────────────────────────────────
export interface User {
  id: number
  username: string
  type: string
  level: number
  regtime: string
}

export interface SystemInfo {
  php_version: string
  think_version: string
  server_software: string
  os: string
  db_version: string
  upload_max_filesize: string
  post_max_size: string
  memory_limit: string
  max_execution_time: string
  date: string
}

export interface SystemConfig {
  [key: string]: unknown
}

export interface DnsAccount {
  id: number
  name: string
  type: string
  status: number
  created_at: string
  domain_count?: number
}

export interface DomainItem {
  id: number
  name: string
  account_id: number
  account_name?: string
  type?: string
  status: number
  is_notice?: number
  is_hide?: number
  is_sso?: number
  remark?: string
  record_count?: number
  created_at: string
}

export interface DnsRecord {
  id: number
  domain_id: number
  name: string
  type: string
  value: string
  line?: string
  ttl: number
  mx?: number
  weight?: number
  status: number
  remark?: string
  created_at?: string
}

export interface Certificate {
  id: number
  domain: string
  status: number
  isauto: number
  fullchain?: string
  privatekey?: string
  processid?: string
  error?: string
  expire_time?: string
  created_at: string
}

export interface DeployTask {
  id: number
  name: string
  cert_id: number
  status: number
  active: number
  last_run?: string
}

export interface MonitorTask {
  id: number
  did: string
  rr: string
  domain?: string
  recordid: string
  recordinfo: string
  type: number
  main_value: string
  backup_value: string
  checktype: number
  checkurl: string
  tcpport?: number
  frequency: number
  cycle: number
  timeout: number
  proxy: number
  cdn: number
  remark: string
  status: number
  active: number
  checktimestr?: string
  addtimestr?: string
}

export interface MonitorLog {
  id: number
  taskid: number
  action: number
  date: string
  error: string
}

export interface MonitorOverview {
  total: number
  active: number
  healthy: number
  unhealthy: number
  running?: number
  run_state?: number
}

export interface UserItem {
  id: number
  username: string
  type: string
  level: number
  status: number
  regtime: string
}

export interface LogItem {
  id: number
  uid: number
  username: string
  action: string
  detail: string
  ip: string
  created_at: string
}

export default apiClient
