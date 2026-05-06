<template>
  <div class="p-6 max-w-5xl">
    <h2 class="text-xl font-bold text-gray-100 mb-1">Barèmes kilométriques</h2>
    <p class="text-sm text-gray-400 mb-6">Taux officiels par année fiscale — voitures, motos, cyclomoteurs.</p>

    <div v-if="loading" class="text-gray-400 text-sm">Chargement…</div>

    <div v-else class="space-y-3">
      <div
        v-for="row in rows" :key="row.year"
        class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden"
      >
        <!-- Header ligne -->
        <div class="px-5 py-4 flex items-center justify-between">
          <span class="text-white font-semibold text-lg">{{ row.year }}</span>
          <div class="flex items-center gap-3">
            <span v-if="row.savedAt" class="text-xs text-emerald-400">✓ Enregistré</span>
            <button
              @click="row.open = !row.open"
              class="px-4 py-1.5 text-sm font-medium bg-gray-700 text-gray-200 rounded-lg hover:bg-gray-600 transition-colors"
            >
              {{ row.open ? 'Fermer' : 'Modifier' }}
            </button>
          </div>
        </div>

        <!-- Éditeur dépliable -->
        <div v-if="row.open" class="border-t border-gray-700 px-5 py-5 space-y-6">

          <!-- Voitures -->
          <div>
            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">
              Voitures (thermiques, hybrides, hydrogène)
            </h3>
            <div class="grid grid-cols-[4rem_1fr_1fr_1fr_1fr] gap-2 text-xs text-gray-500 uppercase tracking-wider mb-2 px-1">
              <span>CV</span>
              <span>≤5 000 km (€/km)</span>
              <span>5–20k taux (€/km)</span>
              <span>5–20k forfait (€)</span>
              <span>&gt;20 000 km (€/km)</span>
            </div>
            <div
              v-for="cv in [3, 4, 5, 6, 7]" :key="cv"
              class="grid grid-cols-[4rem_1fr_1fr_1fr_1fr] gap-2 items-center mb-1.5"
            >
              <span class="text-gray-300 text-sm font-medium">{{ cv }} CV</span>
              <input v-model.number="row.draft.car[cv].rate1" type="number" step="0.001" :class="inputCls" />
              <input v-model.number="row.draft.car[cv].rate2" type="number" step="0.001" :class="inputCls" />
              <input v-model.number="row.draft.car[cv].fixed2" type="number" step="1" :class="inputCls" />
              <input v-model.number="row.draft.car[cv].rate3" type="number" step="0.001" :class="inputCls" />
            </div>
          </div>

          <!-- Motocyclettes -->
          <div>
            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Motocyclettes</h3>
            <div class="grid grid-cols-[6rem_1fr_1fr_1fr_1fr] gap-2 text-xs text-gray-500 uppercase tracking-wider mb-2 px-1">
              <span>Cylindrée</span>
              <span>≤3 000 km</span>
              <span>3–6k taux</span>
              <span>3–6k forfait</span>
              <span>&gt;6 000 km</span>
            </div>
            <div
              v-for="[key, label] in motoGroups" :key="key"
              class="grid grid-cols-[6rem_1fr_1fr_1fr_1fr] gap-2 items-center mb-1.5"
            >
              <span class="text-gray-300 text-sm">{{ label }}</span>
              <input v-model.number="row.draft.motorcycle[key].rate1" type="number" step="0.001" :class="inputCls" />
              <input v-model.number="row.draft.motorcycle[key].rate2" type="number" step="0.001" :class="inputCls" />
              <input v-model.number="row.draft.motorcycle[key].fixed2" type="number" step="1" :class="inputCls" />
              <input v-model.number="row.draft.motorcycle[key].rate3" type="number" step="0.001" :class="inputCls" />
            </div>
          </div>

          <!-- Cyclomoteurs -->
          <div>
            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Cyclomoteurs</h3>
            <div class="grid grid-cols-[6rem_1fr_1fr_1fr_1fr] gap-2 text-xs text-gray-500 uppercase tracking-wider mb-2 px-1">
              <span></span>
              <span>≤3 000 km</span>
              <span>3–6k taux</span>
              <span>3–6k forfait</span>
              <span>&gt;6 000 km</span>
            </div>
            <div class="grid grid-cols-[6rem_1fr_1fr_1fr_1fr] gap-2 items-center">
              <span class="text-gray-300 text-sm">Cyclo</span>
              <input v-model.number="row.draft.moped.rate1" type="number" step="0.001" :class="inputCls" />
              <input v-model.number="row.draft.moped.rate2" type="number" step="0.001" :class="inputCls" />
              <input v-model.number="row.draft.moped.fixed2" type="number" step="1" :class="inputCls" />
              <input v-model.number="row.draft.moped.rate3" type="number" step="0.001" :class="inputCls" />
            </div>
          </div>

          <!-- Multiplicateur électrique -->
          <div class="flex items-center gap-4">
            <label class="text-sm text-gray-300 font-medium">Majoration véhicule électrique</label>
            <input
              v-model.number="row.draft.electricMultiplier"
              type="number" step="0.01" min="1"
              class="w-24 bg-gray-700 border border-gray-600 rounded-lg px-2 py-1.5 text-sm text-white focus:outline-none focus:border-indigo-500"
            />
            <span class="text-gray-500 text-xs">× (ex : 1.20 = +20 %)</span>
          </div>

          <!-- Bouton enregistrer -->
          <div class="flex justify-end pt-2 border-t border-gray-700">
            <button
              @click="save(row)"
              :disabled="row.saving"
              class="px-5 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-500 transition-colors disabled:opacity-40"
            >
              {{ row.saving ? 'Enregistrement…' : 'Enregistrer' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { adminApi, type BaremeKilometrique, type BaremeRates } from '@/api/adminApi'

const motoGroups: [number, string][] = [[1, '1-2 CV'], [3, '3-5 CV'], [6, '+5 CV']]

const inputCls = 'w-full bg-gray-700 border border-gray-600 rounded-lg px-2 py-1.5 text-sm text-white focus:outline-none focus:border-indigo-500'

interface Row {
  year: number
  draft: BaremeRates
  open: boolean
  saving: boolean
  savedAt: boolean
}

function cloneRates(rates: BaremeRates): BaremeRates {
  return JSON.parse(JSON.stringify(rates))
}

const loading = ref(true)
const rows = ref<Row[]>([])

async function load() {
  const baremes = await adminApi.listBaremes()
  rows.value = baremes.map((b: BaremeKilometrique) => ({
    year: b.year,
    draft: cloneRates(b.rates),
    open: false,
    saving: false,
    savedAt: false,
  }))
}

async function save(row: Row) {
  row.saving = true
  try {
    await adminApi.upsertBareme(row.year, row.draft)
    row.savedAt = true
    setTimeout(() => { row.savedAt = false }, 2000)
  } finally {
    row.saving = false
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
