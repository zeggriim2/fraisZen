import http from './http'

export interface AdminStats {
  totalUsers: number
  activeUsers: number
  inactiveUsers: number
  mrr: number
  arr: number
}

export interface AdminUser {
  id: string
  email: string
  roles: string[]
  subscriptionStatus: string | null
  defaultYear: number | null
  defaultFiscalPower: number | null
  createdAt: string
  personCount: number
}

export interface AdminUserDetail extends AdminUser {
  persons: AdminPerson[]
}

export interface AdminPerson {
  id: string
  firstName: string
  lastName: string
  fullName: string
  email: string | null
  createdAt: string
  expenseCount: number
}

export interface AdminUsersResponse {
  items: AdminUser[]
  total: number
  page: number
  pages: number
}

export const adminApi = {
  getStats: () =>
    http.get<AdminStats>('/admin/stats').then(r => r.data),

  getUsers: (params: { search?: string; status?: string; page?: number }) =>
    http.get<AdminUsersResponse>('/admin/users', { params }).then(r => r.data),

  exportCsv: () =>
    http.get('/admin/users/export', { responseType: 'blob' }).then(r => r.data),

  getUser: (id: string) =>
    http.get<AdminUserDetail>(`/admin/users/${id}`).then(r => r.data),

  updateSubscription: (id: string, status: string) =>
    http.patch(`/admin/users/${id}/subscription`, { status }).then(r => r.data),

  deleteUser: (id: string) =>
    http.delete(`/admin/users/${id}`).then(r => r.data),

  impersonate: (id: string) =>
    http.post<{ token: string }>(`/admin/users/${id}/impersonate`).then(r => r.data),
}
