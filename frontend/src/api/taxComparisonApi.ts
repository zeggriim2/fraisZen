import http from './http'
import type { TaxComparison } from '@/types'

export const taxComparisonApi = {
  /** Comparison of a single person: the household dashboard calls it once per person, never aggregated. */
  get: (personId: string, year: number) =>
    http.get<TaxComparison>(`/persons/${personId}/tax-comparison/${year}`).then(r => r.data),
}
