import type { FiscalConfig, ForfaitParametersInput } from '@/api/adminApi'
import { centsToEurosInput, eurosToCents } from '@/utils/money'

/** Form state of the six forfait 10 % parameters: strings bound to the inputs (euros, not cents). */
export interface ForfaitDraft {
  ratePercent: string
  minimumEuros: string
  maximumEuros: string
  version: string
  source: string
  effectiveFrom: string
}

export const FORFAIT_FIELD_LABELS: Record<keyof ForfaitDraft, string> = {
  ratePercent: 'Taux (%)',
  minimumEuros: 'Minimum (€)',
  maximumEuros: 'Plafond (€)',
  version: 'Version',
  source: 'Source',
  effectiveFrom: 'Date d\'entrée en vigueur',
}

export function emptyForfaitDraft(): ForfaitDraft {
  return { ratePercent: '', minimumEuros: '', maximumEuros: '', version: '', source: '', effectiveFrom: '' }
}

export function forfaitDraftFromConfig(config: FiscalConfig): ForfaitDraft {
  return {
    ratePercent: config.forfaitRatePercent === null ? '' : String(config.forfaitRatePercent),
    minimumEuros: centsToEurosInput(config.forfaitMinimumCents),
    maximumEuros: centsToEurosInput(config.forfaitMaximumCents),
    version: config.version ?? '',
    source: config.source ?? '',
    effectiveFrom: config.effectiveFrom ?? '',
  }
}

export function isForfaitDraftEmpty(draft: ForfaitDraft): boolean {
  return (Object.keys(draft) as (keyof ForfaitDraft)[]).every(key => draft[key].trim() === '')
}

export function isSameForfaitDraft(a: ForfaitDraft, b: ForfaitDraft): boolean {
  return (Object.keys(a) as (keyof ForfaitDraft)[]).every(key => a[key].trim() === b[key].trim())
}

export type ForfaitDraftResult =
  | { ok: true; input: ForfaitParametersInput | null }
  | { ok: false; error: string }

/**
 * Turns the form draft into the API payload (euros → integer cents, without float loss: "14555,00" → 1455500).
 * An entirely empty draft is valid and means "do not touch the forfait parameters" (partial configuration).
 * Business rules (1–100 %, min ≤ plafond, blanks…) are enforced by the API (422); only the shape is checked here.
 */
export function buildForfaitInput(draft: ForfaitDraft): ForfaitDraftResult {
  if (isForfaitDraftEmpty(draft)) return { ok: true, input: null }

  const missing = (Object.keys(draft) as (keyof ForfaitDraft)[]).filter(key => draft[key].trim() === '')
  if (missing.length > 0) {
    return { ok: false, error: `Les six paramètres du forfait doivent être renseignés ensemble. Manquant : ${missing.map(k => FORFAIT_FIELD_LABELS[k]).join(', ')}.` }
  }

  const rate = Number(draft.ratePercent.trim().replace(',', '.'))
  if (!Number.isInteger(rate)) return { ok: false, error: 'Le taux doit être un nombre entier de pourcents.' }
  const minimumCents = eurosToCents(draft.minimumEuros)
  const maximumCents = eurosToCents(draft.maximumEuros)
  if (minimumCents === null || maximumCents === null) return { ok: false, error: 'Le minimum et le plafond doivent être des montants en euros.' }

  return {
    ok: true,
    input: {
      forfaitRatePercent: rate,
      forfaitMinimumCents: minimumCents,
      forfaitMaximumCents: maximumCents,
      version: draft.version.trim(),
      source: draft.source.trim(),
      effectiveFrom: draft.effectiveFrom.trim(),
    },
  }
}

/** Human-readable (French) list of the parameters still missing for a year, from the API `missingTaxComparisonFields`. */
export function missingForfaitLabels(fields: string[]): string[] {
  const labels: Record<string, string> = {
    forfait_rate_percent: FORFAIT_FIELD_LABELS.ratePercent,
    forfait_minimum_cents: FORFAIT_FIELD_LABELS.minimumEuros,
    forfait_maximum_cents: FORFAIT_FIELD_LABELS.maximumEuros,
    version: FORFAIT_FIELD_LABELS.version,
    source: FORFAIT_FIELD_LABELS.source,
    effective_from: FORFAIT_FIELD_LABELS.effectiveFrom,
  }
  return fields.map(f => labels[f] ?? f)
}
