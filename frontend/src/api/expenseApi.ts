import http from './http'
import type { CreateExpenseDto, UpdateExpenseDto, Expense, ExpenseSummary, ReceiptVault, ReceiptDocument, ReceiptDocumentPage, ReceiptMatch, ReceiptExpenseCandidate } from '@/types'

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
  downloadPdf: (personId: string, year: number, taxCase = '1AK') =>
    http.get('/expenses/summary/pdf', { params: { personId, year, taxCase }, responseType: 'blob' }).then(r => r.data as Blob),
  downloadCsv: (personId: string, year: number, taxCase = '1AK') =>
    http.get('/expenses/summary/csv', { params: { personId, year, taxCase }, responseType: 'blob' }).then(r => r.data as Blob),

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
    return http.post<{ receiptFilename: string; receiptMimeType: string }>(`/expenses/${id}/receipt`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }).then(r => r.data)
  },
  downloadReceipt: (id: string) =>
    http.get(`/expenses/${id}/receipt`, { responseType: 'blob' }).then(r => r.data as Blob),
  deleteReceipt: (id: string) =>
    http.delete(`/expenses/${id}/receipt`),
  getReceiptVault: (personId: string, year: number, page = 1, pageSize = 6, status = 'all', search = '') =>
    http.get<ReceiptVault>('/receipts', {
      params: { personId, year, page, pageSize, status, search },
    }).then(r => r.data),
  getReceiptDocuments: (personId: string, year: number, page = 1, pageSize = 6) =>
    http.get<ReceiptDocumentPage>('/receipt-documents', { params: { personId, year, page, pageSize } }).then(r => r.data),
  getReceiptDocument: (id: string, personId: string) =>
    http.get<ReceiptDocument>(`/receipt-documents/${id}`, { params: { personId } }).then(r => r.data),
  uploadReceiptDocument: (personId: string, file: File) => {
    const form = new FormData(); form.append('personId', personId); form.append('receipt', file)
    return http.post<ReceiptDocument>('/receipt-documents', form, { headers: { 'Content-Type': 'multipart/form-data' } }).then(r => r.data)
  },
  downloadReceiptDocument: (id: string, personId: string) =>
    http.get(`/receipt-documents/${id}/file`, { params: { personId }, responseType: 'blob' }).then(r => r.data as Blob),
  deleteReceiptDocument: (id: string, personId: string) => http.delete(`/receipt-documents/${id}`, { params: { personId } }),
  reviewReceiptMatch: (documentId: string, matchId: string, personId: string, decision: 'confirm' | 'reject') =>
    http.post<ReceiptMatch>(`/receipt-documents/${documentId}/matches/${matchId}/${decision}`, { personId }).then(r => r.data),
  getReceiptLineCandidates: (documentId: string, lineId: string, personId: string) =>
    http.get<ReceiptExpenseCandidate[]>(`/receipt-documents/${documentId}/lines/${lineId}/candidates`, { params: { personId } }).then(r => r.data),
  attachReceiptLine: (documentId: string, lineId: string, expenseId: string, personId: string) =>
    http.post<ReceiptMatch>(`/receipt-documents/${documentId}/lines/${lineId}/attach`, { expenseId, personId }).then(r => r.data),
  createTollExpenseFromReceiptLine: (documentId: string, lineId: string, personId: string) =>
    http.post<{ expenseId: string; matchId: string }>(`/receipt-documents/${documentId}/lines/${lineId}/create-expense`, { personId }).then(r => r.data),

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
