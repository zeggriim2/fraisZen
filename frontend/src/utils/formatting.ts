const eurFormatter = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' })

export function fmtEur(value: number): string {
  return eurFormatter.format(value)
}
