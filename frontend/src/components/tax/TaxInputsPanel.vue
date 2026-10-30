<template>
  <section class="rounded-2xl border border-gray-200 bg-white p-4" :aria-labelledby="`${baseId}-title`" :data-person-id="person.id">
    <button :id="`${baseId}-title`" type="button" class="flex w-full items-center justify-between text-left font-semibold text-gray-900"
      :aria-expanded="expanded" :aria-controls="`${baseId}-body`" @click="toggle">
      <span>{{ t('tax.inputs.title', { name: person.fullName, year }) }}</span><span aria-hidden="true">{{ expanded ? '−' : '+' }}</span>
    </button>
    <div v-if="expanded" :id="`${baseId}-body`" class="mt-3">
      <p v-if="loading" role="status" class="text-sm text-gray-500">{{ t('tax.inputs.loading') }}</p>
      <p v-else-if="error" role="alert" class="text-sm text-red-700">{{ error }} <button type="button" class="underline" @click="load">{{ t('tax.comparison.retry') }}</button></p>
      <TaxInputsEditor v-if="inputs" :inputs="inputs" :person-id="person.id" :year="year" :busy="saving"
        @add-salary="run(() => taxInputApi.addSalary(person.id, year, $event))"
        @remove-salary="run(() => taxInputApi.removeSalary(person.id, $event))"
        @add-reimbursement="run(() => taxInputApi.addReimbursement(person.id, year, $event))"
        @indicate-reintegration="(id, value) => run(() => taxInputApi.indicateReintegration(person.id, id, value))"
        @unlink-expense="run(() => taxInputApi.unlinkExpense(person.id, $event))"
        @remove-reimbursement="run(() => taxInputApi.removeReimbursement(person.id, $event))" />
    </div>
  </section>
</template>

<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import type { Person, TaxInputs } from '@/types'
import { taxInputApi } from '@/api/taxInputApi'
import { t } from '@/i18n'
import type { AxiosError } from 'axios'
import { focusField } from '@/utils/fieldAnchor'
import { isCurrentResponse } from '@/utils/taxComparison'
import TaxInputsEditor from '@/components/tax/TaxInputsEditor.vue'

/**
 * "Fiscal data" panel of ONE person for ONE year: loads and saves only this person's inputs, then
 * emits `changed` so the parent refreshes this person's comparison card (and only this one).
 * `focusFieldId` is the DOM id targeted by a direct link: the panel opens and focuses that field.
 */
const props = withDefaults(defineProps<{ person: Person; year: number; focusFieldId?: string | null }>(), { focusFieldId: null })
const emit = defineEmits<{ changed: [] }>()

const baseId = `tax-inputs-panel-${props.person.id}`
const expanded = ref(false)
const inputs = ref<TaxInputs | null>(null)
const loading = ref(false)
const saving = ref(false)
const error = ref<string | null>(null)

async function load() {
  const requested = { personId: props.person.id, year: props.year }
  loading.value = true; error.value = null
  try {
    const result = await taxInputApi.get(requested.personId, requested.year)
    if (isCurrentResponse(result, { personId: props.person.id, year: props.year })) inputs.value = result
  } catch {
    error.value = t('tax.inputs.loadError')
  } finally { loading.value = false }
}

async function run(action: () => Promise<unknown>) {
  saving.value = true; error.value = null
  try {
    await action()
    await load()
    emit('changed')
  } catch (e: unknown) {
    // API messages are not translated: only the (translated) nature of the failure is shown.
    error.value = (e as AxiosError)?.response?.status === 422 ? t('tax.inputs.invalid') : t('tax.inputs.saveError')
  } finally { saving.value = false }
}

function toggle() {
  expanded.value = !expanded.value
  if (expanded.value && !inputs.value) load()
}

async function openOnField(id: string | null) {
  if (!id) return
  expanded.value = true
  if (!inputs.value) await load()
  await nextTick()
  await focusField(id)
}

watch(() => props.year, () => { inputs.value = null; if (expanded.value) load() })
watch(() => props.focusFieldId, id => openOnField(id), { immediate: true })
</script>
