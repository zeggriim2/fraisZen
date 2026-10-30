<template>
  <div class="space-y-5 text-sm text-gray-700">
    <!-- Salaires nets imposables -->
    <section :aria-labelledby="`${prefix}-salaries-title`">
      <h4 :id="`${prefix}-salaries-title`" class="font-semibold text-gray-900 mb-2">{{ t('tax.inputs.salaries.title', { year }) }}</h4>
      <p v-if="!inputs.salaries.length" class="text-amber-800 mb-2" data-testid="tax-inputs-no-salary">{{ t('tax.inputs.salaries.empty', { year }) }}</p>
      <ul v-else class="mb-2 space-y-1">
        <li v-for="salary in inputs.salaries" :key="salary.id" class="flex flex-wrap items-center justify-between gap-2" data-testid="tax-inputs-salary">
          <span>{{ t('tax.inputs.salaries.row', { employer: salary.employer, start: salary.periodStart, end: salary.periodEnd, amount: fmtCents(salary.amountCents) }) }}</span>
          <button type="button" class="text-red-700 underline" :disabled="busy" @click="emit('remove-salary', salary.id)">{{ t('tax.inputs.remove') }}</button>
        </li>
      </ul>
      <form class="grid grid-cols-1 sm:grid-cols-2 gap-2" @submit.prevent="submitSalary">
        <label class="flex flex-col gap-1">{{ t('tax.inputs.salaries.employer') }}
          <input :id="fieldAnchor('net-taxable-salary', personId)" v-model.trim="salary.employer" required class="rounded-lg border-gray-300 text-sm" />
        </label>
        <label class="flex flex-col gap-1">{{ t('tax.inputs.salaries.amount') }}
          <input v-model.number="salary.amountEuros" type="number" min="0" step="0.01" required class="rounded-lg border-gray-300 text-sm" />
        </label>
        <label class="flex flex-col gap-1">{{ t('tax.inputs.salaries.periodStart') }}
          <input v-model="salary.periodStart" type="date" :min="`${year}-01-01`" :max="`${year}-12-31`" required class="rounded-lg border-gray-300 text-sm" />
        </label>
        <label class="flex flex-col gap-1">{{ t('tax.inputs.salaries.periodEnd') }}
          <input v-model="salary.periodEnd" type="date" :min="`${year}-01-01`" :max="`${year}-12-31`" required class="rounded-lg border-gray-300 text-sm" />
        </label>
        <p class="sm:col-span-2 text-xs text-gray-500">{{ t('tax.inputs.salaries.help') }}</p>
        <div class="sm:col-span-2"><button type="submit" class="primary-action" :disabled="busy">{{ t('tax.inputs.salaries.add') }}</button></div>
      </form>
    </section>

    <!-- Remboursements employeur -->
    <section :aria-labelledby="`${prefix}-reimbursements-title`">
      <h4 :id="`${prefix}-reimbursements-title`" class="font-semibold text-gray-900 mb-2">{{ t('tax.inputs.reimbursements.title', { year }) }}</h4>
      <p v-if="!inputs.reimbursements.length" class="mb-2 text-gray-500">{{ t('tax.inputs.reimbursements.empty') }}</p>
      <ul v-else class="mb-3 space-y-2">
        <li v-for="r in inputs.reimbursements" :key="r.id" class="rounded-lg border border-gray-200 p-2" data-testid="tax-inputs-reimbursement">
          <p>{{ t('tax.inputs.reimbursements.row', { employer: r.employer, category: categoryLabel(r.category), amount: fmtCents(r.amountCents), date: r.date ?? t('tax.inputs.reimbursements.noDate') }) }}</p>
          <label class="mt-1 flex flex-wrap items-center gap-2">{{ t('tax.inputs.reimbursements.toReintegrate') }}
            <select :id="fieldAnchor('employer-reimbursement', r.id)" :value="reintegrationValue(r.toReintegrate)" :disabled="busy" class="rounded-lg border-gray-300 text-sm"
              @change="emit('indicate-reintegration', r.id, parseReintegration(($event.target as HTMLSelectElement).value))">
              <option value="">{{ t('tax.inputs.reimbursements.notIndicated') }}</option>
              <option value="yes">{{ t('tax.inputs.reimbursements.yes') }}</option>
              <option value="no">{{ t('tax.inputs.reimbursements.no') }}</option>
            </select>
          </label>
          <p v-if="r.expenseId" class="mt-1 flex flex-wrap items-center gap-2" :class="r.linkedExpenseFound === false ? 'text-amber-800' : 'text-gray-500'">
            <span>{{ r.linkedExpenseFound === false ? t('tax.inputs.reimbursements.linkedExpenseMissing', { expenseId: r.expenseId, year }) : t('tax.inputs.reimbursements.linkedExpense') }}</span>
            <button :id="fieldAnchor('employer-reimbursement-expense', r.id)" type="button" class="underline" :disabled="busy" @click="emit('unlink-expense', r.id)">{{ t('tax.inputs.reimbursements.unlink') }}</button>
          </p>
          <button type="button" class="mt-1 text-red-700 underline" :disabled="busy" @click="emit('remove-reimbursement', r.id)">{{ t('tax.inputs.remove') }}</button>
        </li>
      </ul>
      <form class="grid grid-cols-1 sm:grid-cols-2 gap-2" @submit.prevent="submitReimbursement">
        <label class="flex flex-col gap-1">{{ t('tax.inputs.reimbursements.employer') }}
          <input v-model.trim="reimbursement.employer" required class="rounded-lg border-gray-300 text-sm" />
        </label>
        <label class="flex flex-col gap-1">{{ t('tax.inputs.reimbursements.amount') }}
          <input v-model.number="reimbursement.amountEuros" type="number" min="0" step="0.01" required class="rounded-lg border-gray-300 text-sm" />
        </label>
        <label class="flex flex-col gap-1">{{ t('tax.inputs.reimbursements.category') }}
          <select v-model="reimbursement.category" required class="rounded-lg border-gray-300 text-sm">
            <option v-for="c in inputs.categories" :key="c" :value="c">{{ categoryLabel(c) }}</option>
          </select>
        </label>
        <label class="flex flex-col gap-1">{{ t('tax.inputs.reimbursements.date') }}
          <input v-model="reimbursement.date" type="date" :min="`${year}-01-01`" :max="`${year}-12-31`" class="rounded-lg border-gray-300 text-sm" />
        </label>
        <label class="flex flex-col gap-1 sm:col-span-2">{{ t('tax.inputs.reimbursements.toReintegrate') }}
          <select v-model="reimbursement.reintegration" class="rounded-lg border-gray-300 text-sm">
            <option value="">{{ t('tax.inputs.reimbursements.notIndicated') }}</option>
            <option value="yes">{{ t('tax.inputs.reimbursements.yes') }}</option>
            <option value="no">{{ t('tax.inputs.reimbursements.no') }}</option>
          </select>
        </label>
        <div class="sm:col-span-2"><button type="submit" class="primary-action" :disabled="busy">{{ t('tax.inputs.reimbursements.add') }}</button></div>
      </form>
    </section>
  </div>
</template>

<script setup lang="ts">
import { reactive } from 'vue'
import type { NewEmployerReimbursement, NewNetTaxableSalary, TaxInputs } from '@/types'
import { t, hasTranslation } from '@/i18n'
import { fmtCents } from '@/utils/taxComparison'
import { fieldAnchor } from '@/utils/fieldAnchor'

/**
 * Form fields of the comparison inputs of ONE person and ONE year. The DOM ids of the fields are the
 * targets of the "Compléter ce champ" links returned by the comparison API (see fieldAnchor()).
 */
const props = defineProps<{ inputs: TaxInputs; personId: string; year: number; busy?: boolean }>()
const emit = defineEmits<{
  'add-salary': [salary: NewNetTaxableSalary]
  'remove-salary': [salaryId: string]
  'add-reimbursement': [reimbursement: NewEmployerReimbursement]
  'indicate-reintegration': [reimbursementId: string, toReintegrate: boolean | null]
  'unlink-expense': [reimbursementId: string]
  'remove-reimbursement': [reimbursementId: string]
}>()

const prefix = `tax-inputs-${props.personId}`
const salary = reactive({ employer: '', amountEuros: null as number | null, periodStart: `${props.year}-01-01`, periodEnd: `${props.year}-12-31` })
const reimbursement = reactive({ employer: '', amountEuros: null as number | null, category: props.inputs.categories[0] ?? 'other', date: '', reintegration: '' })

const toCents = (euros: number | null) => Math.round((euros ?? 0) * 100)

function reintegrationValue(value: boolean | null): string {
  return value === null ? '' : value ? 'yes' : 'no'
}

function parseReintegration(value: string): boolean | null {
  return value === '' ? null : value === 'yes'
}

function categoryLabel(category: string): string {
  const key = `tax.inputs.categories.${category}`
  return hasTranslation(key) ? t(key) : t('tax.inputs.categories.other')
}

function submitSalary() {
  emit('add-salary', { employer: salary.employer, periodStart: salary.periodStart, periodEnd: salary.periodEnd, amountCents: toCents(salary.amountEuros) })
  salary.employer = ''; salary.amountEuros = null
}

function submitReimbursement() {
  emit('add-reimbursement', {
    employer: reimbursement.employer,
    amountCents: toCents(reimbursement.amountEuros),
    category: reimbursement.category,
    toReintegrate: parseReintegration(reimbursement.reintegration),
    date: reimbursement.date === '' ? null : reimbursement.date,
  })
  reimbursement.employer = ''; reimbursement.amountEuros = null; reimbursement.date = ''; reimbursement.reintegration = ''
}
</script>
