<template>
  <div :data-person-id="comparison.personId">
    <p class="text-xs text-gray-500 mb-3" data-testid="tax-temporal">{{ t(`tax.comparison.temporal.${comparison.display.temporalQualification}`, { year: comparison.year }) }}</p>

    <template v-if="comparison.status === 'available'">
      <!-- Bandeau : uniquement pour un résultat disponible dont l'écart exact est strictement positif (décidé par l'API). -->
      <div v-if="comparison.display.showBanner" role="status" data-testid="tax-banner" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
        <p class="font-semibold">{{ t('tax.comparison.banner.title') }}</p>
        <p class="text-sm mt-1" data-testid="tax-banner-amount">{{ t('tax.comparison.banner.amount', { amount: bannerDifferenceLabel(comparison) }) }}</p>
        <button type="button" class="mt-2 text-sm font-medium underline" data-testid="tax-understand" :aria-expanded="detailOpen" :aria-controls="`${baseId}-detail`" @click="detailOpen = !detailOpen">
          {{ detailOpen ? t('tax.comparison.banner.hide') : t('tax.comparison.banner.understand') }}
        </button>
      </div>
      <div v-else data-testid="tax-no-recommendation" class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
        <p>{{ t(`tax.comparison.outcome.${comparison.display.outcome === 'equivalent' ? 'equivalent' : 'less_advantageous'}`) }}</p>
        <button type="button" class="mt-2 text-sm font-medium text-indigo-600 underline" data-testid="tax-understand" :aria-expanded="detailOpen" :aria-controls="`${baseId}-detail`" @click="detailOpen = !detailOpen">
          {{ detailOpen ? t('tax.comparison.banner.hide') : t('tax.comparison.banner.understand') }}
        </button>
      </div>
      <TaxComparisonDetail v-if="detailOpen" :id="`${baseId}-detail`" :comparison="comparison" />
    </template>

    <div v-else data-testid="tax-missing" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
      <p class="font-medium">
        {{ comparison.status === 'configuration_missing'
          ? t('tax.comparison.status.configuration_missing', { year: comparison.year })
          : t('tax.comparison.status.incomplete') }}
      </p>
      <ul class="mt-2 space-y-1">
        <li v-for="field in comparison.display.missingFields" :key="field.code" class="flex flex-wrap items-center gap-x-2" data-testid="tax-missing-field">
          <span>{{ missingFieldLabel(field, comparison.year) }}</span>
          <RouterLink v-if="field.link" :to="{ path: field.link.path, query: field.link.query, hash: field.link.hash }" class="font-medium underline">
            {{ t('tax.comparison.missing.goTo') }}
          </RouterLink>
          <span v-else-if="field.field.startsWith('fiscal_config')" class="text-xs">{{ t('tax.comparison.missing.adminOnly') }}</span>
        </li>
      </ul>
    </div>

    <p v-if="!detailOpen" class="mt-3 text-xs text-gray-500" role="note" data-testid="tax-disclaimer">{{ t(comparison.display.disclaimerKey) }}</p>
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import type { TaxComparison } from '@/types'
import { t } from '@/i18n'
import { bannerDifferenceLabel, missingFieldLabel } from '@/utils/taxComparison'
import TaxComparisonDetail from '@/components/tax/TaxComparisonDetail.vue'

/** Display of ONE already loaded result (no fetching): banner, outcome, missing fields, detail, disclaimer. */
const props = withDefaults(defineProps<{ comparison: TaxComparison; baseId: string; initiallyOpen?: boolean }>(), { initiallyOpen: false })

const detailOpen = ref(props.initiallyOpen)
watch(() => [props.comparison.personId, props.comparison.year], () => { detailOpen.value = false })
</script>
