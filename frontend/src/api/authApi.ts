import http from './http'

export interface AuthUser {
  id: string
  email: string
  roles: string[]
  subscriptionStatus: string | null
  defaultYear: number | null
  defaultFiscalPower: number | null
  createdAt: string
}

export interface UpdateProfilePayload {
  email: string
  defaultYear: number | null
  defaultFiscalPower: number | null
}

export const authApi = {
  register: (email: string, password: string) =>
    http.post<AuthUser>('/auth/register', { email, password }).then(r => r.data),
  login: (email: string, password: string) =>
    http.post<{ token: string }>('/auth/login', { email, password }).then(r => r.data),
  me: () => http.get<AuthUser>('/auth/me').then(r => r.data),
  updateProfile: (payload: UpdateProfilePayload) =>
    http.patch('/auth/me', payload).then(r => r.data),
  updatePassword: (currentPassword: string, newPassword: string) =>
    http.patch('/auth/me/password', { currentPassword, newPassword }).then(r => r.data),
  deleteAccount: () =>
    http.delete('/auth/me').then(r => r.data),
  forgotPassword: (email: string) =>
    http.post('/auth/forgot-password', { email }).then(r => r.data),
  resetPassword: (token: string, newPassword: string) =>
    http.post('/auth/reset-password', { token, newPassword }).then(r => r.data),
}
