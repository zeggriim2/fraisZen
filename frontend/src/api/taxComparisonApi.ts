import http from './http'
import type { TaxClosureSummary, TaxComparison } from '@/types'

export const taxComparisonApi = {
  /** Comparison of a single person: the household dashboard calls it once per person, never aggregated. */
  get: (personId: string, year: number) =>
    http.get<TaxComparison>(`/persons/${personId}/tax-comparison/${year}`).then(r => r.data),
  /** Validates and closes the reviewed calculation (409 if it changed since it was displayed). */
  close: (personId: string, year: number, calculationFingerprint: string) =>
    http.post<TaxClosureSummary>(`/persons/${personId}/tax-comparison/${year}/closure`, { confirm: true, calculationFingerprint }).then(r => r.data),
  /** Explicitly reopens the closure in force; the snapshot stays in the history. */
  reopen: (personId: string, year: number, version: number) =>
    http.post<TaxClosureSummary>(`/persons/${personId}/tax-comparison/${year}/closure/reopen`, { confirm: true, version }).then(r => r.data),
}
