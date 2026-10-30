import type { TaxComparison, TaxComparisonMissingField } from '@/types'
import { t } from '@/i18n'
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
