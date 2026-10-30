import http from './http'
import type { EmployerReimbursementInput, NetTaxableSalaryInput, NewEmployerReimbursement, NewNetTaxableSalary, TaxInputs } from '@/types'

/** Comparison inputs of ONE person (salaries, employer reimbursements); every call is scoped to the person. */
export const taxInputApi = {
  get: (personId: string, year: number) =>
    http.get<TaxInputs>(`/persons/${personId}/tax-inputs/${year}`).then(r => r.data),
  addSalary: (personId: string, year: number, data: NewNetTaxableSalary) =>
    http.post<NetTaxableSalaryInput>(`/persons/${personId}/tax-inputs/${year}/salaries`, data).then(r => r.data),
  removeSalary: (personId: string, salaryId: string) =>
    http.delete(`/persons/${personId}/tax-inputs/salaries/${salaryId}`),
  addReimbursement: (personId: string, year: number, data: NewEmployerReimbursement) =>
    http.post<EmployerReimbursementInput>(`/persons/${personId}/tax-inputs/${year}/reimbursements`, data).then(r => r.data),
  indicateReintegration: (personId: string, reimbursementId: string, toReintegrate: boolean | null) =>
    http.patch<EmployerReimbursementInput>(`/persons/${personId}/tax-inputs/reimbursements/${encodeURIComponent(reimbursementId)}`, { toReintegrate }).then(r => r.data),
  unlinkExpense: (personId: string, reimbursementId: string) =>
    http.patch<EmployerReimbursementInput>(`/persons/${personId}/tax-inputs/reimbursements/${encodeURIComponent(reimbursementId)}`, { unlinkExpense: true }).then(r => r.data),
  removeReimbursement: (personId: string, reimbursementId: string) =>
    http.delete(`/persons/${personId}/tax-inputs/reimbursements/${encodeURIComponent(reimbursementId)}`),
}
