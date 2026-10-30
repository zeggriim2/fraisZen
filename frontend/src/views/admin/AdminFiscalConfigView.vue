<template>
  <div class="workspace-page admin-workspace">
    <AdminSectionNav />
    <div class="workspace-title"><div><span class="eyebrow">PARAMÈTRES ANNUELS</span><h2>Les bons repères pour chaque année.</h2><p>Télétravail, repas et paramètres du forfait 10 % : gérez les montants utilisés dans les calculs et la comparaison forfait / frais réels.</p></div></div>

    <div v-if="loading" class="text-gray-400 text-sm">Chargement…</div>

    <div v-else class="space-y-3">
      <!-- Header -->
      <div class="fiscal-columns">
        <span>Année</span>
        <span>Télétravail (€/jour)</span>
        <span>Repas domicile (€/repas)</span>
        <span></span>
        <span></span>
      </div>

      <div
        v-for="row in rows" :key="row.year"
        class="fiscal-year-card" :data-testid="`fiscal-year-${row.year}`"
      >
        <div class="fiscal-year-row">
          <span class="text-gray-900 font-semibold">{{ row.year }}</span>

          <div class="fiscal-field flex items-center gap-2">
            <span class="fiscal-field-label">Télétravail</span><input
              :aria-label="`Télétravail par jour ${row.year}`" v-model.number="row.draftAllowance"
              type="number" step="0.01" min="0"
              class="w-24 bg-gray-100 border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-900 focus:outline-none focus:border-indigo-500"
            />
            <span class="text-gray-400 text-xs">€/j</span>
          </div>

          <div class="fiscal-field flex items-center gap-2"><span class="fiscal-field-label">Repas domicile</span>
            <input
              :aria-label="`Repas domicile ${row.year}`" v-model.number="row.draftMeal"
              type="number" step="0.01" min="0"
              class="w-24 bg-gray-100 border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-900 focus:outline-none focus:border-indigo-500"
            />
            <span class="text-gray-400 text-xs">€/r</span>
          </div>

          <button
            @click="save(row)"
            :disabled="row.saving || !isDirty(row)"
            class="px-4 py-1.5 text-sm font-medium rounded-lg transition-colors disabled:opacity-40"
            :class="isDirty(row) ? 'bg-indigo-600 text-white hover:bg-indigo-500' : 'bg-gray-100 text-gray-400'"
          >
            {{ row.saving ? 'Enreg…' : 'Enregistrer' }}
          </button>

          <span class="text-xs text-emerald-400 text-right">{{ row.savedAt ? '✓' : '' }}</span>
        </div>

        <AdminForfaitFields
          v-model="row.draftForfait"
          :label="String(row.year)"
          :id-suffix="String(row.year)"
          :complete="row.config.taxComparisonComplete"
          :missing-fields="row.config.missingTaxComparisonFields"
          :error="row.error"
        />
      </div>

      <!-- Add a new year -->
      <div class="bg-white border border-dashed border-gray-300 rounded-xl px-5 py-4">
        <p class="text-xs text-gray-400 font-medium mb-3 uppercase tracking-wider">Ajouter une année</p>
        <div class="flex items-center gap-3 flex-wrap">
          <input v-model.number="newYear" type="number" placeholder="Année" min="2020" max="2099" aria-label="Nouvelle année"
            class="w-24 bg-gray-100 border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-900 focus:outline-none focus:border-indigo-500" />
          <div class="flex items-center gap-1">
            <input v-model.number="newAllowance" type="number" step="0.01" min="0" placeholder="€/jour télétravail" aria-label="Télétravail par jour (nouvelle année)"
              class="w-36 bg-gray-100 border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-900 focus:outline-none focus:border-indigo-500" />
          </div>
          <div class="flex items-center gap-1">
            <input v-model.number="newMealValue" type="number" step="0.01" min="0" placeholder="€/repas domicile" aria-label="Repas domicile (nouvelle année)"
              class="w-36 bg-gray-100 border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-900 focus:outline-none focus:border-indigo-500" />
          </div>
          <button
            @click="addYear"
            :disabled="!newYear || !newAllowance || !newMealValue || adding"
            class="px-4 py-1.5 text-sm font-medium bg-emerald-600 text-white rounded-lg hover:bg-emerald-500 transition-colors disabled:opacity-40"
          >
            {{ adding ? 'Ajout…' : 'Ajouter' }}
          </button>
        </div>
        <AdminForfaitFields
          v-model="newForfait"
          label="la nouvelle année"
          id-suffix="new"
          :complete="false"
          :error="addError"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import AdminSectionNav from '@/components/ui/AdminSectionNav.vue'
import AdminForfaitFields from '@/components/admin/AdminForfaitFields.vue'
import { ref, onMounted } from 'vue'
import { adminApi, type FiscalConfig } from '@/api/adminApi'
import { extractApiError } from '@/utils/apiError'
import { buildForfaitInput, emptyForfaitDraft, forfaitDraftFromConfig, isSameForfaitDraft, type ForfaitDraft } from '@/utils/fiscalConfigForm'

interface Row {
  year: number
  config: FiscalConfig
  draftAllowance: number
  savedAllowance: number
  draftMeal: number
  savedMeal: number
  draftForfait: ForfaitDraft
  savedForfait: ForfaitDraft
  saving: boolean
  savedAt: boolean
  error: string
}

const loading = ref(true)
const rows = ref<Row[]>([])
const newYear = ref<number | null>(null)
const newAllowance = ref<number | null>(null)
const newMealValue = ref<number | null>(null)
const newForfait = ref<ForfaitDraft>(emptyForfaitDraft())
const adding = ref(false)
const addError = ref('')

function toRow(c: FiscalConfig, savedAt = false): Row {
  const forfait = forfaitDraftFromConfig(c)
  return {
    year: c.year,
    config: c,
    draftAllowance: c.remoteWorkDailyAllowance,
    savedAllowance: c.remoteWorkDailyAllowance,
    draftMeal: c.homeMealValue,
    savedMeal: c.homeMealValue,
    draftForfait: forfait,
    savedForfait: { ...forfait },
    saving: false,
    savedAt,
    error: '',
  }
}

function isDirty(row: Row): boolean {
  return row.draftAllowance !== row.savedAllowance
    || row.draftMeal !== row.savedMeal
    || !isSameForfaitDraft(row.draftForfait, row.savedForfait)
}

async function load() {
  const configs = await adminApi.listFiscalConfigs()
  rows.value = configs.map(c => toRow(c))
}

async function save(row: Row) {
  row.error = ''
  const forfait = buildForfaitInput(row.draftForfait)
  if (!forfait.ok) {
    row.error = forfait.error
    return
  }
  row.saving = true
  try {
    const config = await adminApi.upsertFiscalConfig(row.year, { remoteWorkDailyAllowance: row.draftAllowance, homeMealValue: row.draftMeal, forfait: forfait.input })
    Object.assign(row, toRow(config, true))
    setTimeout(() => { row.savedAt = false }, 2000)
  } catch (e) {
    // 422 from the API: domain rule violated (taux 1–100, minimum ≤ plafond, montants ≥ 0, version/source non vides) — nothing was saved.
    row.error = extractApiError(e, 'Enregistrement impossible.')
  } finally {
    row.saving = false
  }
}

async function addYear() {
  if (!newYear.value || !newAllowance.value || !newMealValue.value) return
  if (rows.value.some(r => r.year === newYear.value)) {
    addError.value = 'Cette année existe déjà.'
    return
  }
  addError.value = ''
  const forfait = buildForfaitInput(newForfait.value)
  if (!forfait.ok) {
    addError.value = forfait.error
    return
  }
  adding.value = true
  try {
    const config = await adminApi.upsertFiscalConfig(newYear.value, { remoteWorkDailyAllowance: newAllowance.value, homeMealValue: newMealValue.value, forfait: forfait.input })
    rows.value.unshift(toRow(config, true))
    setTimeout(() => { if (rows.value[0]) rows.value[0].savedAt = false }, 2000)
    newYear.value = null
    newAllowance.value = null
    newMealValue.value = null
    newForfait.value = emptyForfaitDraft()
  } catch (e) {
    addError.value = extractApiError(e, 'Ajout impossible.')
  } finally {
    adding.value = false
  }
}

onMounted(async () => {
  try {
    await load()
  } finally {
    loading.value = false
  }
})
</script>
