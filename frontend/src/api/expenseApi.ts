import http from './http'
import type { CreateExpenseDto, UpdateExpenseDto, Expense, ExpenseSummary } from '@/types'

export interface TrancheTaux {
  rate1: number
  rate2: number
  fixed2: number
  rate3: number
}

export interface BaremeYear {
  year: number
  rates: {
    car: Record<number, TrancheTaux>
    motorcycle: Record<number, TrancheTaux>
    moped: TrancheTaux
    electricMultiplier: number
  }
}

export const expenseApi = {
  getByPeriod: (from: string, to: string, personId?: string) => {
    const params: Record<string, string> = { from, to }
    if (personId) params.personId = personId
    return http.get<Expense[]>('/expenses', { params }).then(r => r.data)
  },
  getSummary: (personId: string, year: number) =>
    http.get<ExpenseSummary>('/expenses/summary', { params: { personId, year } }).then(r => r.data),
  create: (data: CreateExpenseDto) => http.post('/expenses', data),
  update: (id: string, data: UpdateExpenseDto) => http.patch(`/expenses/${id}`, data),
  remove: (id: string) => http.delete(`/expenses/${id}`),
  downloadPdf: (personId: string, year: number) =>
    http.get('/expenses/summary/pdf', { params: { personId, year }, responseType: 'blob' }).then(r => r.data as Blob),
  downloadCsv: (personId: string, year: number) =>
    http.get('/expenses/summary/csv', { params: { personId, year }, responseType: 'blob' }).then(r => r.data as Blob),

  getFiscalConfig: (year: number) =>
    http.get<{ year: number; remoteWorkDailyAllowance: number; homeMealValue: number }>(`/expenses/fiscal-config/${year}`).then(r => r.data),

  getBareme: (year: number) =>
    http.get<BaremeYear>(`/baremes/${year}`).then(r => r.data),

  getDistance: (fromLat: number, fromLng: number, toLat: number, toLng: number) =>
    http.get<{ distanceKm: number }>('/expenses/distance', {
      params: { fromLat, fromLng, toLat, toLng },
    }).then(r => r.data.distanceKm),

  uploadReceipt: (id: string, file: File) => {
    const form = new FormData()
    form.append('receipt', file)
    return http.post<{ receiptFilename: string }>(`/expenses/${id}/receipt`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }).then(r => r.data)
  },
  downloadReceipt: (id: string) =>
    http.get(`/expenses/${id}/receipt`, { responseType: 'blob' }).then(r => r.data as Blob),
  deleteReceipt: (id: string) =>
    http.delete(`/expenses/${id}/receipt`),

  bulkCreateTravel: (payload: {
    personId: string
    dates: string[]
    distanceKm: number
    vehiclePower?: number | null
    departure?: string | null
    arrival?: string | null
    description?: string | null
    roundTrip?: boolean
    vehicleType?: string
    isElectric?: boolean
  }) => http.post<{ created: number }>('/expenses/bulk-travel', payload).then(r => r.data),
}

export function getPublicHolidays(year: number): Promise<Record<string, string>> {
  return http.get<Record<string, string>>(`/public-holidays/${year}`).then(r => r.data)
}
