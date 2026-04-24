import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', component: () => import('@/views/LoginView.vue'), meta: { public: true } },
    { path: '/register', component: () => import('@/views/RegisterView.vue'), meta: { public: true } },
    { path: '/', redirect: '/calendar' },
    { path: '/calendar', component: () => import('@/views/CalendarView.vue') },
    { path: '/summary', component: () => import('@/views/SummaryView.vue') },
    { path: '/persons', component: () => import('@/views/PersonsView.vue') },
  ],
})

router.beforeEach(to => {
  const token = localStorage.getItem('jwt_token')
  if (!to.meta.public && !token) return '/login'
})

export default router
