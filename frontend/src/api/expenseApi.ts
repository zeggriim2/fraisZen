import http from './http'
import type { CreateExpenseDto, UpdateExpenseDto, Expense, ExpenseSummary } from '@/types'

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
}
