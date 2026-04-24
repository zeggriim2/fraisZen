import axios from 'axios'
import type { CreateExpenseDto, Expense, ExpenseSummary } from '@/types'

const http = axios.create({ baseURL: '/api' })

export const expenseApi = {
  getByPeriod: (from: string, to: string, personId?: string) => {
    const params: Record<string, string> = { from, to }
    if (personId) params.personId = personId
    return http.get<Expense[]>('/expenses', { params }).then(r => r.data)
  },
  getSummary: (personId: string, year: number) =>
    http.get<ExpenseSummary>('/expenses/summary', { params: { personId, year } }).then(r => r.data),
  create: (data: CreateExpenseDto) => http.post('/expenses', data),
  remove: (id: string) => http.delete(`/expenses/${id}`),
}
