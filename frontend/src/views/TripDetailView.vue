<template>
  <div class="p-4 sm:p-6">
    <!-- Header -->
    <div class="flex items-center gap-3 mb-6">
      <router-link to="/summary" class="text-gray-400 hover:text-gray-600 text-lg leading-none">←</router-link>
      <h2 class="text-xl font-semibold text-gray-900">Détail des trajets</h2>
      <span v-if="personStore.activePerson" class="text-sm text-gray-400">— {{ personStore.activePerson.fullName }}</span>
    </div>

    <div v-if="!personStore.activePerson" class="bg-amber-50 border border-amber-200 rounded-xl p-5 text-sm text-amber-700">
      Sélectionnez une personne dans le menu latéral.
    </div>

    <template v-else>
      <!-- Filtres -->
      <div class="flex flex-wrap gap-3 mb-6">
        <select v-model="selectedYear" @change="load"
          class="rounded-lg border-gray-300 shadow-sm text-sm px-3 py-2">
          <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
        </select>

        <select v-model="selectedMonth"
          class="rounded-lg border-gray-300 shadow-sm text-sm px-3 py-2">
          <option :value="0">Tous les mois</option>
          <option v-for="(label, i) in MONTHS" :key="i + 1" :value="i + 1">{{ label }}</option>
        </select>

        <select v-model="selectedCv"
          class="rounded-lg border-gray-300 shadow-sm text-sm px-3 py-2">
          <option :value="0">Toutes puissances</option>
          <option v-for="cv in [3, 4, 5, 6, 7]" :key="cv" :value="cv">{{ cv }} CV</option>
        </select>
      </div>

      <!-- Stats rapides -->
      <div class="grid grid-cols-3 gap-4 mb-6" v-if="filteredTrips.length">
        <div class="bg-blue-50 rounded-2xl p-4 border border-blue-100">
          <p class="text-xs text-blue-500 font-medium uppercase tracking-wide mb-1">Trajets</p>
          <p class="text-2xl font-bold text-blue-700">{{ filteredTrips.length }}</p>
        </div>
        <div class="bg-blue-50 rounded-2xl p-4 border border-blue-100">
          <p class="text-xs text-blue-500 font-medium uppercase tracking-wide mb-1">Distance totale</p>
          <p class="text-2xl font-bold text-blue-700">{{ totalKm.toFixed(0) }} km</p>
        </div>
        <div class="bg-indigo-50 rounded-2xl p-4 border border-indigo-100">
          <p class="text-xs text-indigo-500 font-medium uppercase tracking-wide mb-1">Déduction estimée</p>
          <p class="text-2xl font-bold text-indigo-700">{{ fmt(totalDeduction) }}</p>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="flex justify-center py-20">
        <div class="w-8 h-8 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin"></div>
      </div>

      <!-- Tableau -->
      <div v-else-if="filteredTrips.length" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100 bg-gray-50">
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500">Date</th>
              <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500">Trajet</th>
              <th class="px-3 py-3 text-right text-xs font-semibold text-gray-500">Distance</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500">CV</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-gray-500">A/R</th>
              <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500">Déduction</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="(t, i) in filteredTrips" :key="i" class="hover:bg-gray-50 transition-colors">
              <td class="px-5 py-3 text-gray-400 text-xs whitespace-nowrap">{{ t.date }}</td>
              <td class="px-5 py-3 text-gray-700">
                <span v-if="t.departure && t.arrival">{{ t.departure }} → {{ t.arrival }}</span>
                <span v-else class="italic text-gray-400">{{ t.description ?? '—' }}</span>
              </td>
              <td class="px-3 py-3 text-right text-gray-600 whitespace-nowrap">{{ t.distanceKm }} km</td>
              <td class="px-3 py-3 text-center">
                <span class="text-xs font-medium bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">
                  {{ t.vehiclePower ?? '—' }} CV
                </span>
              </td>
              <td class="px-3 py-3 text-center">
                <span v-if="t.roundTrip" class="text-xs font-medium bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full">A/R</span>
                <span v-else class="text-gray-300 text-xs">—</span>
              </td>
              <td class="px-5 py-3 text-right font-medium text-indigo-600 whitespace-nowrap">
                {{ fmt(tripDeduction(t)) }}
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="border-t-2 border-gray-200 bg-indigo-50">
              <td colspan="2" class="px-5 py-3 text-sm font-semibold text-gray-700">Total ({{ filteredTrips.length }} trajets)</td>
              <td class="px-3 py-3 text-right text-sm font-semibold text-gray-700">{{ totalKm.toFixed(0) }} km</td>
              <td colspan="2"></td>
              <td class="px-5 py-3 text-right text-sm font-bold text-indigo-700">{{ fmt(totalDeduction) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div v-else class="bg-white rounded-2xl border border-gray-200 p-12 text-center text-gray-400">
        Aucun trajet trouvé pour cette période.
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { usePersonStore } from '@/stores/personStore'
import { expenseApi } from '@/api/expenseApi'
import type { TravelExpense } from '@/types'

const MONTHS = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre']

const BAREME: Record<number, { rate1: number; rate2: number; fixed2: number; rate3: number }> = {
  3: { rate1: 0.456, rate2: 0.273, fixed2: 915,  rate3: 0.318 },
  4: { rate1: 0.523, rate2: 0.294, fixed2: 1147, rate3: 0.352 },
  5: { rate1: 0.548, rate2: 0.308, fixed2: 1200, rate3: 0.368 },
  6: { rate1: 0.574, rate2: 0.323, fixed2: 1256, rate3: 0.386 },
  7: { rate1: 0.601, rate2: 0.340, fixed2: 1301, rate3: 0.405 },
}

const personStore = usePersonStore()
const route = useRoute()

const now = new Date().getFullYear()
const years = Array.from({ length: 6 }, (_, i) => now - i)

const selectedYear  = ref(parseInt(route.query.year as string) || now)
const selectedMonth = ref(0)
const selectedCv    = ref(0)
const loading       = ref(false)
const trips         = ref<TravelExpense[]>([])

const filteredTrips = computed(() => {
  return trips.value.filter(t => {
    if (selectedMonth.value !== 0) {
      const month = new Date(t.date).getMonth() + 1
      if (month !== selectedMonth.value) return false
    }
    if (selectedCv.value !== 0 && t.vehiclePower !== selectedCv.value) return false
    return true
  })
})

// Annual km totals per bucket (unfiltered) — determines which barème tranche applies
const annualKmByBucket = computed(() => {
  const buckets: Record<string, number> = {}
  for (const t of trips.value) {
    const key = bucketKey(t)
    buckets[key] = (buckets[key] ?? 0) + t.distanceKm
  }
  return buckets
})

function bucketKey(t: TravelExpense) {
  const cv = Math.min(Math.max(t.vehiclePower ?? 5, 3), 7)
  return `${t.vehicleType}|${cv}|${t.isElectric ? 1 : 0}`
}

function tripDeduction(t: TravelExpense): number {
  const cv = Math.min(Math.max(t.vehiclePower ?? 5, 3), 7)
  const annualKm = annualKmByBucket.value[bucketKey(t)] ?? t.distanceKm
  const b = BAREME[cv] ?? BAREME[5]

  // Rate determined by annual total, applied proportionally per trip
  let rate: number
  if (annualKm <= 5000)       rate = b.rate1
  else if (annualKm <= 20000) rate = b.rate2 + b.fixed2 / annualKm
  else                        rate = b.rate3

  const deduction = t.distanceKm * rate
  return t.isElectric ? deduction * 1.20 : deduction
}

const totalKm = computed(() => filteredTrips.value.reduce((s, t) => s + t.distanceKm, 0))
const totalDeduction = computed(() => filteredTrips.value.reduce((s, t) => s + tripDeduction(t), 0))

const fmt = (v: number) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(v)

async function load() {
  if (!personStore.activePerson) return
  loading.value = true
  try {
    const from = `${selectedYear.value}-01-01`
    const to   = `${selectedYear.value}-12-31`
    const data = await expenseApi.getByPeriod(from, to, personStore.activePerson.id)
    trips.value = data.filter((e): e is TravelExpense => e.type === 'travel')
  } finally {
    loading.value = false
  }
}

watch(() => personStore.activePerson, load)
watch(selectedYear, load)
onMounted(load)
</script>
