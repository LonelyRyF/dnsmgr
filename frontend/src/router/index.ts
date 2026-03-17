import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory('/spa/'),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/pages/LoginPage.vue'),
      meta: { public: true },
    },
    {
      path: '/',
      component: () => import('@/components/layout/AppLayout.vue'),
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('@/pages/DashboardPage.vue'),
          meta: { title: '控制台' },
        },
        {
          path: 'domains',
          name: 'domains',
          component: () => import('@/pages/DomainsPage.vue'),
          meta: { title: '域名管理' },
        },
        {
          path: 'domains/:id/records',
          name: 'records',
          component: () => import('@/pages/RecordsPage.vue'),
          meta: { title: 'DNS 记录' },
        },
        {
          path: 'accounts',
          name: 'accounts',
          component: () => import('@/pages/AccountsPage.vue'),
          meta: { title: 'DNS 账户' },
        },
        {
          path: 'certificates',
          name: 'certificates',
          component: () => import('@/pages/CertificatesPage.vue'),
          meta: { title: 'SSL 证书' },
        },
        {
          path: 'deploy-tasks',
          name: 'deploy-tasks',
          component: () => import('@/pages/DeployTasksPage.vue'),
          meta: { title: '部署任务' },
        },
        {
          path: 'monitor',
          name: 'monitor',
          component: () => import('@/pages/MonitorPage.vue'),
          meta: { title: '容灾监控' },
        },
        {
          path: 'schedule',
          name: 'schedule',
          component: () => import('@/pages/SchedulePage.vue'),
          meta: { title: '定时任务' },
        },
        {
          path: 'users',
          name: 'users',
          component: () => import('@/pages/UsersPage.vue'),
          meta: { title: '用户管理', adminOnly: true },
        },
        {
          path: 'settings',
          name: 'settings',
          component: () => import('@/pages/SystemSettingsPage.vue'),
          meta: { title: '系统设置', adminOnly: true },
        },
        {
          path: 'logs',
          name: 'logs',
          component: () => import('@/pages/LogsPage.vue'),
          meta: { title: '操作日志' },
        },
      ],
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: '/',
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  // 如果没有 JWT token 且不是公开页面，尝试用 cookie 换取
  if (!auth.isAuthenticated && !to.meta.public) {
    const exchanged = await auth.exchangeToken()
    if (!exchanged) {
      return { name: 'login' }
    }
  }

  if (to.name === 'login' && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }
})

export default router
