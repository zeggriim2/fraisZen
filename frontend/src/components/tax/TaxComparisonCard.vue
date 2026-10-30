<template>
  <section class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5" :aria-labelledby="`${baseId}-title`" :data-person-id="person.id">
    <header class="flex flex-wrap items-baseline justify-between gap-2 mb-2">
      <h3 :id="`${baseId}-title`" class="font-semibold text-gray-900">
        {{ t('tax.comparison.title') }}<template v-if="showPersonName"> · {{ person.fullName }}</template>
      </h3>
      <span class="text-xs text-gray-500">{{ t('tax.comparison.yearLabel', { year }) }}</span>
    </header>

    <p v-if="loading" role="status" class="text-sm text-gray-500">{{ t('tax.comparison.loading') }}</p>
    <p v-else-if="error" role="alert" class="text-sm text-red-700">
      {{ t('tax.comparison.error') }} <button class="underline" @click="load">{{ t('tax.comparison.retry') }}</button>
    </p>

    <template v-else-if="comparison">
      <p class="text-xs text-gray-500 mb-3">{{ t(`tax.comparison.temporal.${comparison.display.temporalQualification}`, { year: comparison.year }) }}</p>

      <template v-if="comparison.status === 'available'">
        <div v-if="comparison.display.showBanner" role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
          <p class="font-semibold">{{ t('tax.comparison.banner.title') }}</p>
          <p class="text-sm mt-1" data-testid="tax-banner-amount">{{ t('tax.comparison.banner.amount', { amount: bannerDifferenceLabel(comparison) }) }}</p>
          <button class="mt-2 text-sm font-medium underline" :aria-expanded="detailOpen" :aria-controls="`${baseId}-detail`" @click="detailOpen = !detailOpen">
            {{ detailOpen ? t('tax.comparison.banner.hide') : t('tax.comparison.banner.understand') }}
          </button>
        </div>
        <div v-else class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
          <p>{{ t(`tax.comparison.outcome.${comparison.display.outcome === 'equivalent' ? 'equivalent' : 'less_advantageous'}`) }}</p>
          <button class="mt-2 text-sm font-medium text-indigo-600 underline" :aria-expanded="detailOpen" :aria-controls="`${baseId}-detail`" @click="detailOpen = !detailOpen">
            {{ detailOpen ? t('tax.comparison.banner.hide') : t('tax.comparison.banner.understand') }}
          </button>
        </div>
        <TaxComparisonDetail v-if="detailOpen" :id="`${baseId}-detail`" :comparison="comparison" />
      </template>

      <div v-else class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
        <p class="font-medium">
          {{ comparison.status === 'configuration_missing'
            ? t('tax.comparison.status.configuration_missing', { year: comparison.year })
            : t('tax.comparison.status.incomplete') }}
        </p>
        <ul class="mt-2 space-y-1">
          <li v-for="field in comparison.display.missingFields" :key="field.code" class="flex flex-wrap items-center gap-x-2">
            <span>{{ missingFieldLabel(field, comparison.year) }}</span>
            <RouterLink v-if="field.link" :to="{ path: field.link.path, query: field.link.query, hash: field.link.hash }" class="font-medium underline">
              {{ t('tax.comparison.missing.goTo') }}
            </RouterLink>
            <span v-if="field.link?.path.startsWith('/admin')" class="text-xs">{{ t('tax.comparison.missing.adminOnly') }}</span>
          </li>
        </ul>
      </div>

      <p v-if="!detailOpen" class="mt-3 text-xs text-gray-500" role="note">{{ t(comparison.display.disclaimerKey) }}</p>
    </template>
  </section>
</template>

<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import type { Person, TaxComparison } from '@/types'
import { taxComparisonApi } from '@/api/taxComparisonApi'
import { t } from '@/i18n'
import { bannerDifferenceLabel, missingFieldLabel } from '@/utils/taxComparison'
import TaxComparisonDetail from '@/components/tax/TaxComparisonDetail.vue'

/** Comparison card of ONE person: it fetches and displays only this person's result. */
const props = withDefaults(defineProps<{ person: Person; year: number; showPersonName?: boolean }>(), { showPersonName: false })

const comparison = ref<TaxComparison | null>(null)
const loading = ref(false)
const error = ref(false)
const detailOpen = ref(false)
const baseId = `tax-comparison-${props.person.id}`

async function load() {
  const requested = { personId: props.person.id, year: props.year }
  loading.value = true; error.value = false; detailOpen.value = false
  try {
    const result = await taxComparisonApi.get(requested.personId, requested.year)
    // Ignore a late answer for a previous person/year so another person's result is never shown here.
    if (result.personId === props.person.id && result.year === props.year) comparison.value = result
  } catch {
    if (requested.personId === props.person.id && requested.year === props.year) { comparison.value = null; error.value = true }
  } finally { loading.value = false }
}

watch(() => [props.person.id, props.year], () => { comparison.value = null; load() })
onMounted(load)
</script>
