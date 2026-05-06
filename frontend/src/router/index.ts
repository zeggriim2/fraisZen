import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/login', component: () => import('@/views/LoginView.vue'), meta: { public: true } },
    { path: '/register', component: () => import('@/views/RegisterView.vue'), meta: { public: true } },
    { path: '/pricing', component: () => import('@/views/PricingView.vue'), meta: { public: false } },
    { path: '/settings', component: () => import('@/views/SettingsView.vue') },
    { path: '/', redirect: '/calendar' },
    { path: '/calendar', component: () => import('@/views/CalendarView.vue') },
    { path: '/summary', component: () => import('@/views/SummaryView.vue') },
    { path: '/trips', component: () => import('@/views/TripDetailView.vue') },
    { path: '/persons', component: () => import('@/views/PersonsView.vue') },
    { path: '/admin', redirect: '/admin/dashboard', meta: { admin: true } },
    { path: '/admin/dashboard', component: () => import('@/views/admin/AdminDashboardView.vue'), meta: { admin: true } },
    { path: '/admin/users', component: () => import('@/views/admin/AdminUsersView.vue'), meta: { admin: true } },
    { path: '/admin/users/:id', component: () => import('@/views/admin/AdminUserDetailView.vue'), meta: { admin: true } },
    { path: '/admin/fiscal-config', component: () => import('@/views/admin/AdminFiscalConfigView.vue'), meta: { admin: true } },
    { path: '/admin/bareme-kilometrique', component: () => import('@/views/admin/AdminBaremeKilometriqueView.vue'), meta: { admin: true } },
  ],
})

router.beforeEach(async to => {
  const token = localStorage.getItem('jwt_token') ?? sessionStorage.getItem('jwt_token')
  if (!to.meta.public && !token) return '/login'

  if (token) {
    const authStore = useAuthStore()
    if (!authStore.user) await authStore.fetchMe()

    if (to.meta.admin && !authStore.user?.roles.includes('ROLE_ADMIN')) return '/calendar'
  }
})

export default router
