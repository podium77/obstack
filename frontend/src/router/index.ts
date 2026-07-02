import { createRouter, createWebHistory, RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes: RouteRecordRaw[] = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/LoginView.vue')
  },
  {
    path: '/',
    component: () => import('@/components/Layout.vue'),
    meta: { requiresAuth: true },
    children: [
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: () => import('@/views/DashboardView.vue')
      },
      {
        path: 'connections',
        name: 'Connections',
        component: () => import('@/views/DatabaseConnectionsView.vue')
      },
      {
        path: 'connections/:id',
        name: 'ConnectionDetail',
        component: () => import('@/views/ConnectionDetailView.vue')
      },
      {
        path: 'browser/:id',
        name: 'DatabaseBrowser',
        component: () => import('@/views/DatabaseBrowserView.vue')
      },
      {
        path: 'query/:id',
        name: 'QueryExecutor',
        component: () => import('@/views/QueryExecutorView.vue')
      },
      {
        path: 'audit',
        name: 'AuditLogs',
        component: () => import('@/views/AuditLogsView.vue')
      }
    ]
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: '/dashboard'
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// Navigation guards
router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()
  
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next('/login')
  } else if (to.path === '/login' && authStore.isAuthenticated) {
    next('/dashboard')
  } else {
    next()
  }
})

export default router
