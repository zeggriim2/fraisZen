/**
 * Euro ⇄ cent conversions for amounts typed by the admin (euros) and stored by the API (integer cents).
 * String inputs accept both "14555.00" and the French form "14 555,00".
 */
export function eurosToCents(value: number | string | null | undefined): number | null {
  if (value === null || value === undefined) return null
  const normalized = typeof value === 'number'
    ? value
    : Number(value.replace(/\s|\u00a0|\u202f/g, '').replace(',', '.'))
  if (typeof value === 'string' && value.trim() === '') return null
  if (!Number.isFinite(normalized)) return null
  // Round to the cent after trimming float artefacts (14555 * 100 = 1455500.0000000002, 1.005 * 100 = 100.49999999999999).
  return Math.round(Number((normalized * 100).toPrecision(15)))
}

export function centsToEuros(cents: number | null | undefined): number | null {
  if (cents === null || cents === undefined) return null
  return Math.round(cents) / 100
}

/** Value shown in an `<input type="number" step="0.01">` for a cent amount ("" when absent). */
export function centsToEurosInput(cents: number | null | undefined): string {
  const euros = centsToEuros(cents)
  return euros === null ? '' : euros.toFixed(2)
}
