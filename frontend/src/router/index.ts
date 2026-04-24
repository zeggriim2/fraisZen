import { createRouter, createWebHistory } from 'vue-router'

export default createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/calendar' },
    { path: '/calendar', component: () => import('@/views/CalendarView.vue') },
    { path: '/summary', component: () => import('@/views/SummaryView.vue') },
    { path: '/persons', component: () => import('@/views/PersonsView.vue') },
  ],
})
