<template>
  <section class="mt-3 rounded-xl border p-4 text-sm" :class="closed ? 'border-indigo-200 bg-indigo-50 text-indigo-900' : 'border-gray-200 bg-white text-gray-700'" data-testid="tax-closure" :aria-labelledby="`${baseId}-closure-title`">
    <h4 :id="`${baseId}-closure-title`" class="font-semibold">
      <span v-if="closed" data-testid="tax-closure-badge" class="mr-2 inline-block rounded-full bg-indigo-600 px-2 py-0.5 text-xs font-semibold text-white">{{ t('tax.closure.closedBadge') }}</span>{{ t('tax.closure.title', { year: comparison.year }) }}
    </h4>

    <template v-if="closed">
      <p class="mt-1" data-testid="tax-closure-notice">{{ t('tax.closure.closedNotice', { date: fmtClosureDate(closure.closedAt), version: closure.version ?? '' }) }}</p>

      <div v-if="closure.afterClosure?.dataChanged" role="status" data-testid="tax-closure-changed" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-amber-900">
        <p class="font-medium">{{ t('tax.closure.changedAfterClosure') }}</p>
        <p class="mt-1" data-testid="tax-closure-current">{{ currentLabel }}</p>
        <p class="mt-1">{{ t('tax.closure.reopenToRecalculate') }}</p>
      </div>
      <p v-else class="mt-2 text-xs" data-testid="tax-closure-unchanged">{{ t('tax.closure.unchangedSinceClosure') }}</p>

      <div class="mt-3">
        <button v-if="!confirming" type="button" class="font-medium underline" data-testid="tax-closure-reopen" :disabled="busy" @click="confirming = true">{{ t('tax.closure.reopen') }}</button>
        <div v-else role="group" :aria-label="t('tax.closure.reopen')" data-testid="tax-closure-confirm" class="rounded-lg border border-indigo-200 bg-white p-3 text-gray-800">
          <p>{{ t('tax.closure.confirmReopen', { version: closure.version ?? '' }) }}</p>
          <div class="mt-2 flex flex-wrap gap-2">
            <button type="button" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-white disabled:opacity-50" :disabled="busy" @click="confirmReopen">{{ t('tax.closure.confirmReopenButton') }}</button>
            <button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5" :disabled="busy" @click="confirming = false">{{ t('tax.closure.cancel') }}</button>
          </div>
        </div>
      </div>
    </template>

    <template v-else>
      <template v-if="closure.closable && closure.calculationFingerprint">
        <p class="mt-1">{{ t('tax.closure.openNotice') }}</p>
        <div class="mt-3">
          <button v-if="!confirming" type="button" class="rounded-lg bg-indigo-600 px-3 py-1.5 font-medium text-white disabled:opacity-50" data-testid="tax-closure-close" :disabled="busy" @click="confirming = true">{{ t('tax.closure.validateAndClose') }}</button>
          <div v-else role="group" :aria-label="t('tax.closure.validateAndClose')" data-testid="tax-closure-confirm" class="rounded-lg border border-gray-200 bg-gray-50 p-3">
            <p>{{ t('tax.closure.confirmClose', { year: comparison.year }) }}</p>
            <div class="mt-2 flex flex-wrap gap-2">
              <button type="button" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-white disabled:opacity-50" :disabled="busy" @click="confirmClose">{{ t('tax.closure.confirmCloseButton') }}</button>
              <button type="button" class="rounded-lg border border-gray-300 px-3 py-1.5" :disabled="busy" @click="confirming = false">{{ t('tax.closure.cancel') }}</button>
            </div>
          </div>
        </div>
      </template>
      <p v-else class="mt-1 text-xs text-gray-500" data-testid="tax-closure-not-closable">{{ t('tax.closure.notClosable') }}</p>
    </template>

    <p v-if="error" role="alert" class="mt-2 text-red-700" data-testid="tax-closure-error">{{ t(error === 'conflict' ? 'tax.closure.conflict' : 'tax.closure.error') }}</p>

    <template v-if="closure.history.length">
      <p class="mt-3 text-xs font-semibold uppercase tracking-wider opacity-70">{{ t('tax.closure.historyTitle') }}</p>
      <ul class="mt-1 space-y-0.5 text-xs" data-testid="tax-closure-history">
        <li v-for="entry in closure.history" :key="entry.version">{{ historyLabel(entry) }}</li>
      </ul>
    </template>
  </section>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import type { TaxClosureHistoryEntry, TaxComparison } from '@/types'
import { t } from '@/i18n'
import { fmtClosureDate, fmtCents } from '@/utils/taxComparison'

/**
 * Validation / closing / explicit reopening of the displayed year. No fetching here: the parent
 * performs the request on `close` (with the fingerprint of the reviewed calculation) or `reopen`.
 * A closed result is presented as a frozen estimate, never as the final tax calculation.
 */
const props = withDefaults(defineProps<{ comparison: TaxComparison; baseId: string; busy?: boolean; error?: 'conflict' | 'generic' | null }>(), { busy: false, error: null })
const emit = defineEmits<{ close: [fingerprint: string]; reopen: [version: number] }>()

const confirming = ref(false)
const closure = computed(() => props.comparison.closure)
const closed = computed(() => closure.value.state === 'closed')

watch(() => [props.comparison.personId, props.comparison.year, closure.value.state, closure.value.version], () => { confirming.value = false })

const currentLabel = computed(() => {
  const after = closure.value.afterClosure
  if (!after || after.currentDifferenceCents === null) return t('tax.closure.currentUnavailable')
  return t('tax.closure.currentDifference', { amount: fmtCents(after.currentDifferenceCents) })
})

function historyLabel(entry: TaxClosureHistoryEntry): string {
  return entry.reopenedAt === null
    ? t('tax.closure.historyClosed', { version: entry.version, closedAt: fmtClosureDate(entry.closedAt) })
    : t('tax.closure.historyReopened', { version: entry.version, closedAt: fmtClosureDate(entry.closedAt), reopenedAt: fmtClosureDate(entry.reopenedAt) })
}

function confirmClose() {
  if (closure.value.calculationFingerprint) emit('close', closure.value.calculationFingerprint)
}

function confirmReopen() {
  if (closure.value.version !== null) emit('reopen', closure.value.version)
}
</script>
