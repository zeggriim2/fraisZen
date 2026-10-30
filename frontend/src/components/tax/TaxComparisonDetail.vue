<template>
  <div :id="id" class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
    <h4 class="font-semibold text-gray-900 mb-3">{{ t('tax.comparison.detail.title') }}</h4>

    <template v-if="amounts">
      <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">{{ t('tax.comparison.detail.amountsTitle') }}</p>
      <dl class="space-y-1 mb-4">
        <div v-for="row in amountRows" :key="row.label" class="flex justify-between gap-4" :class="row.strong ? 'font-semibold text-gray-900 border-t border-gray-200 pt-1' : ''">
          <dt>{{ row.label }}</dt><dd class="text-right whitespace-nowrap">{{ row.value }}</dd>
        </div>
      </dl>
      <p v-if="comparison.display.outcome === 'equivalent'" class="mb-4 text-gray-900">{{ t('tax.comparison.outcome.equivalent') }}</p>

      <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">{{ t('tax.comparison.detail.formulasTitle') }}</p>
      <ul class="list-disc pl-5 space-y-1 mb-4">
        <li v-for="key in formulaKeys" :key="key">{{ t(`tax.comparison.detail.formulas.${key}`) }}</li>
      </ul>
    </template>

    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">{{ t('tax.comparison.detail.referenceTitle') }}</p>
    <dl class="space-y-1 mb-4">
      <div class="flex justify-between gap-4"><dt>{{ t('tax.comparison.detail.year') }}</dt><dd>{{ comparison.year }}</dd></div>
      <div class="flex justify-between gap-4"><dt>{{ t('tax.comparison.detail.version') }}</dt><dd>{{ comparison.configuration?.version ?? t('tax.comparison.detail.none') }}</dd></div>
      <div class="flex justify-between gap-4"><dt>{{ t('tax.comparison.detail.source') }}</dt><dd class="text-right break-all">{{ comparison.configuration?.source ?? t('tax.comparison.detail.none') }}</dd></div>
      <div class="flex justify-between gap-4"><dt>{{ t('tax.comparison.detail.effectiveFrom') }}</dt><dd>{{ comparison.configuration?.effectiveFrom ?? t('tax.comparison.detail.none') }}</dd></div>
    </dl>

    <template v-if="comparison.rules.length">
      <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">{{ t('tax.comparison.detail.rulesTitle') }}</p>
      <ul class="space-y-1 mb-4">
        <li v-for="rule in comparison.rules" :key="rule.code" class="flex flex-wrap justify-between gap-x-4">
          <span>{{ t(`tax.comparison.detail.rule.${rule.code}`) }}<template v-if="rule.value !== null"> : {{ ruleValue(rule.code, rule.value) }}</template></span>
          <span class="text-xs text-gray-500">{{ rule.version }} · {{ rule.source }} · {{ rule.effectiveFrom }}</span>
        </li>
      </ul>
    </template>

    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">{{ t('tax.comparison.detail.originsTitle') }}</p>
    <ul class="list-disc pl-5 space-y-1 mb-4">
      <li>{{ t('tax.comparison.detail.origins.netTaxableSalary', { entries: comparison.origins.netTaxableSalary.entries, employers: comparison.origins.netTaxableSalary.employers.join(', ') || t('tax.comparison.detail.none') }) }}</li>
      <li>{{ t('tax.comparison.detail.origins.realExpenses', { entries: comparison.origins.realExpenses.entries, trips: comparison.origins.realExpenses.trips, year: comparison.year }) }}</li>
      <li v-if="comparison.origins.realExpenses.travelAllowanceCents !== null">{{ t('tax.comparison.detail.origins.travelAllowance', { amount: fmtCents(comparison.origins.realExpenses.travelAllowanceCents) }) }}</li>
      <li>{{ t('tax.comparison.detail.origins.reimbursements', { entries: comparison.origins.reimbursements.entries, reintegrated: comparison.origins.reimbursements.reintegrated }) }}</li>
      <li>{{ t('tax.comparison.detail.origins.forfaitParameters', { year: comparison.configuration?.year ?? comparison.year }) }}</li>
    </ul>

    <template v-if="comparison.warnings.length">
      <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">{{ t('tax.comparison.detail.warningsTitle') }}</p>
      <ul class="list-disc pl-5 space-y-1 mb-4">
        <li v-for="(warning, i) in comparison.warnings" :key="i" lang="en">{{ warning }}</li>
      </ul>
    </template>

    <p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg p-3" role="note">{{ t(comparison.display.disclaimerKey) }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { TaxComparison } from '@/types'
import { t } from '@/i18n'
import { fmtCents } from '@/utils/taxComparison'

const props = defineProps<{ comparison: TaxComparison; id: string }>()

const amounts = computed(() => props.comparison.amounts)
const formulaKeys = ['theoreticalForfait', 'finalForfait', 'baseForfait', 'baseReal', 'difference'] as const

const amountRows = computed(() => {
  const a = amounts.value
  if (!a) return []
  return [
    { label: t('tax.comparison.detail.netTaxableSalary'), value: fmtCents(a.netTaxableSalaryCents) },
    { label: t('tax.comparison.detail.theoreticalForfait', { rate: a.forfaitRatePercent }), value: fmtCents(a.theoreticalForfaitCents) },
    { label: t('tax.comparison.detail.forfaitMinimum'), value: fmtCents(a.forfaitMinimumCents) },
    { label: t('tax.comparison.detail.forfaitMaximum'), value: fmtCents(a.forfaitMaximumCents) },
    { label: `${t('tax.comparison.detail.finalForfait')} (${t(`tax.comparison.detail.forfaitLimit.${a.forfaitLimitApplied}`)})`, value: fmtCents(a.forfaitCents) },
    { label: t('tax.comparison.detail.realExpenses'), value: fmtCents(a.realExpensesCents) },
    { label: t('tax.comparison.detail.reintegrated'), value: fmtCents(a.reintegratedReimbursementsCents) },
    { label: t('tax.comparison.detail.excludedContribution'), value: fmtCents(a.excludedAlreadyDeductedContributionCents) },
    { label: t('tax.comparison.detail.baseForfait'), value: fmtCents(a.baseForfaitCents) },
    { label: t('tax.comparison.detail.baseReal'), value: fmtCents(a.baseRealExpensesCents) },
    { label: t('tax.comparison.detail.difference'), value: fmtCents(a.differenceCents), strong: true },
  ]
})

function ruleValue(code: string, value: number): string {
  return code.endsWith('_cents') ? fmtCents(value) : String(value)
}
</script>
