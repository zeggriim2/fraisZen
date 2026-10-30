import type { TaxComparison, TaxComparisonMissingField, TaxComparisonWarning } from '@/types'
import { t, hasTranslation } from '@/i18n'
import { fmtEur } from '@/utils/formatting'

const wholeEuroFormatter = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0, minimumFractionDigits: 0 })

export function fmtCents(cents: number): string {
  return fmtEur(cents / 100)
}

/** Difference displayed in the banner: already rounded to the nearest euro by the API. */
export function bannerDifferenceLabel(comparison: TaxComparison): string {
  if (comparison.display.differenceBelowOneEuro) return t('tax.comparison.banner.belowOneEuro')
  return wholeEuroFormatter.format(comparison.display.roundedDifferenceEuros ?? 0)
}

export function missingFieldLabel(field: TaxComparisonMissingField, year: number): string {
  return t(`tax.comparison.missing.${field.field}`, { year, code: field.code, ...field.params })
}

/** Engine warning rendered through the translation catalog; cents parameters become formatted amounts. */
export function warningLabel(warning: TaxComparisonWarning): string {
  const params: Record<string, string> = { ...warning.params }
  if (warning.params.amountCents !== undefined) params.amount = fmtCents(Number(warning.params.amountCents))
  const key = `tax.comparison.warning.${warning.code}`
  return hasTranslation(key) ? t(key, params) : t('tax.comparison.warning.unknown')
}

/**
 * A response is displayed only if it still answers the person and year currently shown by the card:
 * a late answer for a previous person/year must never be displayed under another person.
 */
export function isCurrentResponse(response: { personId: string; year: number }, current: { personId: string; year: number }): boolean {
  return response.personId === current.personId && response.year === current.year
}
