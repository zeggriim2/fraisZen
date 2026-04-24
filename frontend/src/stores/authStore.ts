import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { authApi, type AuthUser } from '@/api/authApi'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem('jwt_token'))
  const user = ref<AuthUser | null>(null)

  const isAuthenticated = computed(() => !!token.value)

  async function login(email: string, password: string): Promise<void> {
    const data = await authApi.login(email, password)
    token.value = data.token
    localStorage.setItem('jwt_token', data.token)
    await fetchMe()
  }

  async function register(email: string, password: string): Promise<void> {
    await authApi.register(email, password)
    await login(email, password)
  }

  async function fetchMe(): Promise<void> {
    if (!token.value) return
    user.value = await authApi.me()
  }

  function logout(): void {
    token.value = null
    user.value = null
    localStorage.removeItem('jwt_token')
  }

  return { token, user, isAuthenticated, login, register, fetchMe, logout }
})
