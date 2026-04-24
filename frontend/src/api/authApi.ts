import http from './http'

export interface AuthUser {
  id: string
  email: string
  roles: string[]
  subscriptionStatus: string | null
  createdAt: string
}

export const authApi = {
  register: (email: string, password: string) =>
    http.post<AuthUser>('/auth/register', { email, password }).then(r => r.data),
  login: (email: string, password: string) =>
    http.post<{ token: string }>('/auth/login', { email, password }).then(r => r.data),
  me: () => http.get<AuthUser>('/auth/me').then(r => r.data),
}
