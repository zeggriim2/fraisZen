<template>
  <div class="p-6 max-w-2xl">
    <h2 class="text-xl font-bold text-gray-100 mb-1">Configuration fiscale</h2>
    <p class="text-sm text-gray-400 mb-6">Indemnité journalière de télétravail par année fiscale (barème URSSAF).</p>

    <div v-if="loading" class="text-gray-400 text-sm">Chargement…</div>

    <div v-else class="space-y-3">
      <div
        v-for="row in rows" :key="row.year"
        class="bg-gray-800 border border-gray-700 rounded-xl px-5 py-4 flex items-center gap-4"
      >
        <span class="text-white font-semibold w-16 shrink-0">{{ row.year }}</span>

        <div class="flex-1 flex items-center gap-2">
          <input
            v-model.number="row.draft"
            type="number" step="0.01" min="0"
            class="w-28 bg-gray-700 border border-gray-600 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-indigo-500"
          />
          <span class="text-gray-400 text-sm">€ / jour</span>
        </div>

        <button
          @click="save(row)"
          :disabled="row.saving || row.draft === row.saved"
          class="px-4 py-1.5 text-sm font-medium rounded-lg transition-colors disabled:opacity-40"
          :class="row.draft !== row.saved ? 'bg-indigo-600 text-white hover:bg-indigo-500' : 'bg-gray-700 text-gray-400'"
        >
          {{ row.saving ? 'Enregistrement…' : 'Enregistrer' }}
        </button>

        <span v-if="row.saved !== null" class="text-xs text-emerald-400 w-16 text-right">
          {{ row.savedAt ? '✓ Sauvegardé' : '' }}
        </span>
      </div>

      <!-- Add a new year -->
      <div class="bg-gray-800 border border-dashed border-gray-600 rounded-xl px-5 py-4">
        <p class="text-xs text-gray-400 font-medium mb-3 uppercase tracking-wider">Ajouter une année</p>
        <div class="flex items-center gap-3">
          <input
            v-model.number="newYear"
            type="number" placeholder="Année" min="2020" max="2099"
            class="w-24 bg-gray-700 border border-gray-600 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-indigo-500"
          />
          <input
            v-model.number="newAllowance"
            type="number" step="0.01" min="0" placeholder="€/jour"
            class="w-28 bg-gray-700 border border-gray-600 rounded-lg px-3 py-1.5 text-sm text-white focus:outline-none focus:border-indigo-500"
          />
          <span class="text-gray-400 text-sm">€ / jour</span>
          <button
            @click="addYear"
            :disabled="!newYear || !newAllowance || adding"
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
  draft: number
  saved: number
  saving: boolean
  savedAt: boolean
}

const loading = ref(true)
const rows = ref<Row[]>([])
const newYear = ref<number | null>(null)
const newAllowance = ref<number | null>(null)
const adding = ref(false)
const addError = ref('')

async function load() {
  const configs = await adminApi.listFiscalConfigs()
  rows.value = configs.map((c: FiscalConfig) => ({
    year: c.year,
    draft: c.remoteWorkDailyAllowance,
    saved: c.remoteWorkDailyAllowance,
    saving: false,
    savedAt: false,
  }))
}

async function save(row: Row) {
  row.saving = true
  try {
    await adminApi.upsertFiscalConfig(row.year, row.draft)
    row.saved = row.draft
    row.savedAt = true
    setTimeout(() => { row.savedAt = false }, 2000)
  } finally {
    row.saving = false
  }
}

async function addYear() {
  if (!newYear.value || !newAllowance.value) return
  if (rows.value.some(r => r.year === newYear.value)) {
    addError.value = 'Cette année existe déjà.'
    return
  }
  addError.value = ''
  adding.value = true
  try {
    const config = await adminApi.upsertFiscalConfig(newYear.value, newAllowance.value)
    rows.value.unshift({ year: config.year, draft: config.remoteWorkDailyAllowance, saved: config.remoteWorkDailyAllowance, saving: false, savedAt: true })
    setTimeout(() => { if (rows.value[0]) rows.value[0].savedAt = false }, 2000)
    newYear.value = null
    newAllowance.value = null
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