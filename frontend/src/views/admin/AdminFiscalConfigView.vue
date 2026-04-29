<template>
  <div class="p-6 max-w-3xl">
    <h2 class="text-xl font-bold text-gray-100 mb-1">Configuration fiscale</h2>
    <p class="text-sm text-gray-400 mb-6">Barèmes fiscaux par année — télétravail (URSSAF) et repas (impots.gouv.fr).</p>

    <div v-if="loading" class="text-gray-400 text-sm">Chargement…</div>

    <div v-else class="space-y-3">
      <!-- Header -->
      <div class="grid grid-cols-[4rem_1fr_1fr_auto_4rem] gap-4 px-5 text-xs font-semibold text-gray-500 uppercase tracking-wider">
        <span>Année</span>
        <span>Télétravail (€/jour)</span>
        <span>Repas domicile (€/repas)</span>
        <span></span>
        <span></span>
      </div>

      <div
        v-for="row in rows" :key="row.year"
        class="bg-gray-800 border border-gray-700 rounded-xl px-5 py-4 grid grid-cols-[4rem_1fr_1fr_auto_4rem] items-center gap-4"
      >
        <span class="text-white font-semibold">{{ row.year }}</span>

        <div class="flex items-center gap-2">
          <input
            v-model.number="row.draftAllowance"
            type="number" step="0.01" min="0"
            class="w-24 bg-gray-700 border border-gray-600 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-indigo-500"
          />
          <span class="text-gray-400 text-xs">€/j</span>
        </div>

        <div class="flex items-center gap-2">
          <input
            v-model.number="row.draftMeal"
            type="number" step="0.01" min="0"
            class="w-24 bg-gray-700 border border-gray-600 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-indigo-500"
          />
          <span class="text-gray-400 text-xs">€/r</span>
        </div>

        <button
          @click="save(row)"
          :disabled="row.saving || (row.draftAllowance === row.savedAllowance && row.draftMeal === row.savedMeal)"
          class="px-4 py-1.5 text-sm font-medium rounded-lg transition-colors disabled:opacity-40"
          :class="(row.draftAllowance !== row.savedAllowance || row.draftMeal !== row.savedMeal) ? 'bg-indigo-600 text-white hover:bg-indigo-500' : 'bg-gray-700 text-gray-400'"
        >
          {{ row.saving ? 'Enreg…' : 'Enregistrer' }}
        </button>

        <span class="text-xs text-emerald-400 text-right">{{ row.savedAt ? '✓' : '' }}</span>
      </div>

      <!-- Add a new year -->
      <div class="bg-gray-800 border border-dashed border-gray-600 rounded-xl px-5 py-4">
        <p class="text-xs text-gray-400 font-medium mb-3 uppercase tracking-wider">Ajouter une année</p>
        <div class="flex items-center gap-3 flex-wrap">
          <input v-model.number="newYear" type="number" placeholder="Année" min="2020" max="2099"
            class="w-24 bg-gray-700 border border-gray-600 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-indigo-500" />
          <div class="flex items-center gap-1">
            <input v-model.number="newAllowance" type="number" step="0.01" min="0" placeholder="€/jour télétravail"
              class="w-36 bg-gray-700 border border-gray-600 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-indigo-500" />
          </div>
          <div class="flex items-center gap-1">
            <input v-model.number="newMealValue" type="number" step="0.01" min="0" placeholder="€/repas domicile"
              class="w-36 bg-gray-700 border border-gray-600 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-indigo-500" />
          </div>
          <button
            @click="addYear"
            :disabled="!newYear || !newAllowance || !newMealValue || adding"
            class="px-4 py-1.5 text-sm font-medium bg-emerald-600 text-white rounded-lg hover:bg-emerald-500 transition-colors disabled:opacity-40"
          >
            {{ adding ? 'Ajout…' : 'Ajouter' }}
          </button>
        </div>
        <p v-if="addError" class="text-xs text-red-400 mt-2">{{ addError }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { adminApi, type FiscalConfig } from '@/api/adminApi'

interface Row {
  year: number
  draftAllowance: number
  savedAllowance: number
  draftMeal: number
  savedMeal: number
  saving: boolean
  savedAt: boolean
}

const loading = ref(true)
const rows = ref<Row[]>([])
const newYear = ref<number | null>(null)
const newAllowance = ref<number | null>(null)
const newMealValue = ref<number | null>(null)
const adding = ref(false)
const addError = ref('')

async function load() {
  const configs = await adminApi.listFiscalConfigs()
  rows.value = configs.map((c: FiscalConfig) => ({
    year: c.year,
    draftAllowance: c.remoteWorkDailyAllowance,
    savedAllowance: c.remoteWorkDailyAllowance,
    draftMeal: c.homeMealValue,
    savedMeal: c.homeMealValue,
    saving: false,
    savedAt: false,
  }))
}

async function save(row: Row) {
  row.saving = true
  try {
    await adminApi.upsertFiscalConfig(row.year, row.draftAllowance, row.draftMeal)
    row.savedAllowance = row.draftAllowance
    row.savedMeal = row.draftMeal
    row.savedAt = true
    setTimeout(() => { row.savedAt = false }, 2000)
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
  adding.value = true
  try {
    const config = await adminApi.upsertFiscalConfig(newYear.value, newAllowance.value, newMealValue.value)
    rows.value.unshift({ year: config.year, draftAllowance: config.remoteWorkDailyAllowance, savedAllowance: config.remoteWorkDailyAllowance, draftMeal: config.homeMealValue, savedMeal: config.homeMealValue, saving: false, savedAt: true })
    setTimeout(() => { if (rows.value[0]) rows.value[0].savedAt = false }, 2000)
    newYear.value = null
    newAllowance.value = null
    newMealValue.value = null
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
