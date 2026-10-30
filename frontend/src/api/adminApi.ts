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

export interface FiscalConfig {
  year: number
  remoteWorkDailyAllowance: number
  homeMealValue: number
  /** Forfait 10 % parameters (F6). Amounts are integer cents; all six are null while not configured. */
  forfaitRatePercent: number | null
  forfaitMinimumCents: number | null
  forfaitMaximumCents: number | null
  version: string | null
  source: string | null
  /** ISO date `YYYY-MM-DD`. */
  effectiveFrom: string | null
  taxComparisonComplete: boolean
  missingTaxComparisonFields: string[]
}

/** Optional group: either the six parameters are all provided, or none (the API rejects a partial group with 422). */
export interface ForfaitParametersInput {
  forfaitRatePercent: number
  forfaitMinimumCents: number
  forfaitMaximumCents: number
  version: string
  source: string
  effectiveFrom: string
}

export interface FiscalConfigInput {
  remoteWorkDailyAllowance: number
  homeMealValue: number
  forfait?: ForfaitParametersInput | null
}

export interface TrancheTaux {
  rate1: number
  rate2: number
  fixed2: number
  rate3: number
}

export interface BaremeRates {
  car: Record<number, TrancheTaux>
  motorcycle: Record<number, TrancheTaux>
  moped: TrancheTaux
  electricMultiplier: number
}

export interface BaremeKilometrique {
  year: number
  rates: BaremeRates
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

  listFiscalConfigs: () =>
    http.get<FiscalConfig[]>('/admin/fiscal-config').then(r => r.data),

  upsertFiscalConfig: (year: number, input: FiscalConfigInput) =>
    http.put<FiscalConfig>(`/admin/fiscal-config/${year}`, {
      remoteWorkDailyAllowance: input.remoteWorkDailyAllowance,
      homeMealValue: input.homeMealValue,
      ...(input.forfait ?? {}),
    }).then(r => r.data),

  listBaremes: () =>
    http.get<BaremeKilometrique[]>('/admin/bareme-kilometrique').then(r => r.data),

  upsertBareme: (year: number, rates: BaremeRates) =>
    http.put<BaremeKilometrique>(`/admin/bareme-kilometrique/${year}`, { rates }).then(r => r.data),
}
