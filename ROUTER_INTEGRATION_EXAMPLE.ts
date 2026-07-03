// Exemple d'intégration dans frontend/src/router/index.ts

import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'

// Import des vues
import LoginView from '@/views/LoginView.vue'
import DashboardView from '@/views/DashboardView.vue'
import CompanyManagementView from '@/views/CompanyManagementView.vue'  // ← NOUVEAU
// ... autres imports

const routes: RouteRecordRaw[] = [
  // Routes publiques
  {
    path: '/login',
    name: 'Login',
    component: LoginView,
    meta: { requiresAuth: false }
  },

  // Routes protégées
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: DashboardView,
    meta: {
      requiresAuth: true,
      title: 'Dashboard'
    }
  },

  // ─── NOUVEAU: Route de gestion des companies ───
  {
    path: '/admin/companies',
    name: 'CompanyManagement',
    component: CompanyManagementView,
    meta: {
      requiresAuth: true,
      requiresRole: 'ROLE_SUPERADMIN',  // ← Seulement pour les SUPERADMIN
      title: 'Gestion des Companies',
      description: 'Gérez les entreprises de la plateforme'
    }
  },
  // ────────────────────────────────────────────────

  // ... autres routes
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes
})

// Middleware de protection
router.beforeEach((to, from, next) => {
  const isAuthenticated = !!localStorage.getItem('token')
  const userRole = localStorage.getItem('userRole')

  if (to.meta.requiresAuth && !isAuthenticated) {
    next('/login')
    return
  }

  if (to.meta.requiresRole && userRole !== to.meta.requiresRole) {
    next('/dashboard')
    return
  }

  next()
})

export default router
