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
      {{ t('tax.comparison.error') }} <button type="button" class="underline" @click="load">{{ t('tax.comparison.retry') }}</button>
    </p>
    <TaxComparisonResult v-else-if="comparison" :comparison="comparison" :base-id="baseId" />
  </section>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, computed } from 'vue'
import type { Person, TaxComparison } from '@/types'
import { taxComparisonApi } from '@/api/taxComparisonApi'
import { t } from '@/i18n'
import { isCurrentResponse } from '@/utils/taxComparison'
import TaxComparisonResult from '@/components/tax/TaxComparisonResult.vue'

/**
 * Comparison card of ONE person: it fetches and displays only this person's result.
 * `reloadToken` lets the parent ask for a refresh after the person's inputs changed.
 */
const props = withDefaults(defineProps<{ person: Person; year: number; showPersonName?: boolean; reloadToken?: number }>(), { showPersonName: false, reloadToken: 0 })

const comparison = ref<TaxComparison | null>(null)
const loading = ref(false)
const error = ref(false)
const baseId = computed(() => `tax-comparison-${props.person.id}`)

async function load() {
  const requested = { personId: props.person.id, year: props.year }
  loading.value = true; error.value = false
  try {
    const result = await taxComparisonApi.get(requested.personId, requested.year)
    // Ignore a late answer for a previous person/year so another person's result is never shown here.
    if (isCurrentResponse(result, { personId: props.person.id, year: props.year })) comparison.value = result
  } catch {
    if (isCurrentResponse(requested, { personId: props.person.id, year: props.year })) { comparison.value = null; error.value = true }
  } finally {
    if (isCurrentResponse(requested, { personId: props.person.id, year: props.year })) loading.value = false
  }
}

watch(() => [props.person.id, props.year], () => { comparison.value = null; load() })
watch(() => props.reloadToken, load)
onMounted(load)
</script>
