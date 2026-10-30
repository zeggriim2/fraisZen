/**
 * Direct links to a field ("Compléter ce champ"): the tax comparison API returns a path, a query that
 * opens the right person/year and a hash equal to the DOM id of the field. The id format mirrors
 * TaxComparisonPresenter::anchor() on the backend.
 */
export function fieldAnchor(field: 'net-taxable-salary' | 'employer-reimbursement' | 'employer-reimbursement-expense', id: string): string {
  return `tax-field-${field}-${id}`
}

/** Element id targeted by a route hash ("#tax-field-…"), or null when the hash is not a field link. */
export function fieldIdFromHash(hash: string): string | null {
  const id = hash.startsWith('#') ? hash.slice(1) : hash
  return id.startsWith('tax-field-') ? id : null
}

/**
 * Scrolls to and focuses the field once it is rendered (panels load their data asynchronously).
 * Returns false when the field never appeared, so callers can report it.
 */
export async function focusField(id: string, attempts = 30, delayMs = 100, root: Pick<Document, 'getElementById'> = document): Promise<boolean> {
  for (let i = 0; i < attempts; i++) {
    const element = root.getElementById(id)
    if (element) {
      element.scrollIntoView?.({ block: 'center', behavior: 'smooth' })
      element.focus?.({ preventScroll: true })
      return true
    }
    await new Promise(resolve => setTimeout(resolve, delayMs))
  }
  return false
}
