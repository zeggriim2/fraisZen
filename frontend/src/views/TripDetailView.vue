<template>
  <div class="workspace-page trips-workspace">
    <PageHeader eyebrow="Barème kilométrique" title="Détail des trajets" :description="personStore.activePerson ? personStore.activePerson.fullName : undefined">
      <template #actions>
        <BaseButton variant="secondary" size="sm" icon="arrow-left" to="/summary">Récapitulatif</BaseButton>
      </template>
    </PageHeader>

    <EmptyState v-if="!personStore.activePerson" icon="users" title="Aucun profil sélectionné" description="Sélectionnez une personne dans le menu latéral pour afficher ses trajets.">
      <template #actions><BaseButton to="/persons" variant="secondary">Gérer les profils</BaseButton></template>
    </EmptyState>

    <template v-else>
      <!-- Filtres -->
      <div class="flex flex-wrap gap-3 mb-6" role="group" aria-label="Filtres">
        <div class="form-field">
          <label for="trips-year" class="sr-only">Année</label>
          <select id="trips-year" v-model="selectedYear" @change="load" class="form-control trip-filter-select w-auto">
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
        <div class="form-field">
          <label for="trips-month" class="sr-only">Mois</label>
          <select id="trips-month" v-model="selectedMonth" class="form-control trip-filter-select w-auto">
            <option :value="0">Tous les mois</option>
            <option v-for="(label, i) in MONTHS" :key="i + 1" :value="i + 1">{{ label }}</option>
          </select>
        </div>
        <div class="form-field">
          <label for="trips-cv" class="sr-only">Puissance fiscale</label>
          <select id="trips-cv" v-model="selectedCv" class="form-control trip-filter-select w-auto">
            <option :value="0">Toutes puissances</option>
            <option v-for="cv in [3, 4, 5, 6, 7]" :key="cv" :value="cv">{{ cv }} CV</option>
          </select>
        </div>
      </div>

      <!-- Stats rapides -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6" v-if="filteredTrips.length">
        <div class="card p-4">
          <p class="eyebrow mb-1">Trajets</p>
          <p class="figure text-2xl font-semibold text-ink">{{ filteredTrips.length }}</p>
        </div>
        <div class="card p-4">
          <p class="eyebrow mb-1">Distance totale</p>
          <p class="figure text-2xl font-semibold text-ink">{{ totalKm.toFixed(0) }} km</p>
        </div>
        <div class="card p-4 border-brand-200 bg-brand-50">
          <p class="eyebrow mb-1 !text-brand-700">Déduction estimée</p>
          <p class="figure text-2xl font-semibold text-brand-700">{{ fmt(totalDeduction) }}</p>
        </div>
      </div>

      <!-- Loading -->
      <LoadingState v-if="loading" label="Chargement des trajets…" />

      <!-- Tableau -->
      <div v-else-if="filteredTrips.length" class="card overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <caption class="sr-only">Trajets de l’année {{ selectedYear }}</caption>
            <thead>
              <tr class="border-b border-line bg-surface-muted">
                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold text-ink-muted">Date</th>
                <th scope="col" class="px-5 py-3 text-left text-xs font-semibold text-ink-muted">Trajet</th>
                <th scope="col" class="px-3 py-3 text-right text-xs font-semibold text-ink-muted">Distance</th>
                <th scope="col" class="px-3 py-3 text-center text-xs font-semibold text-ink-muted">CV</th>
                <th scope="col" class="px-3 py-3 text-center text-xs font-semibold text-ink-muted">A/R</th>
                <th scope="col" class="px-5 py-3 text-right text-xs font-semibold text-ink-muted">Déduction</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line">
              <tr v-for="(t, i) in filteredTrips" :key="i" class="hover:bg-surface-muted transition-colors">
                <td class="px-5 py-3 text-ink-muted text-xs whitespace-nowrap">{{ t.date }}</td>
                <td class="px-5 py-3 text-ink">
                  <span v-if="t.departure && t.arrival" class="inline-flex items-center gap-1.5">{{ t.departure }} <AppIcon name="arrow-right" :size="14" class="text-ink-subtle" /> {{ t.arrival }}</span>
                  <span v-else class="italic text-ink-subtle">{{ t.description ?? '—' }}</span>
                </td>
                <td class="px-3 py-3 text-right text-ink-muted whitespace-nowrap">{{ t.distanceKm }} km</td>
                <td class="px-3 py-3 text-center">
                  <span class="badge badge-neutral">{{ t.vehiclePower ?? '—' }} CV</span>
                </td>
                <td class="px-3 py-3 text-center">
                  <span v-if="t.roundTrip" class="badge badge-brand">A/R</span>
                  <span v-else class="text-ink-subtle text-xs" aria-label="Aller simple">—</span>
                </td>
                <td class="px-5 py-3 text-right font-semibold text-brand-700 whitespace-nowrap">
                  {{ fmt(tripDeduction(t)) }}
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="border-t-2 border-line-strong bg-surface-muted">
                <td colspan="2" class="px-5 py-3 text-sm font-semibold text-ink">Total ({{ filteredTrips.length }} trajet{{ filteredTrips.length > 1 ? 's' : '' }})</td>
                <td class="px-3 py-3 text-right text-sm font-semibold text-ink">{{ totalKm.toFixed(0) }} km</td>
                <td colspan="2"></td>
                <td class="px-5 py-3 text-right text-sm font-bold text-brand-700">{{ fmt(totalDeduction) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <EmptyState v-else icon="car" title="Aucun trajet trouvé" description="Aucun trajet ne correspond à cette période ou à ces filtres." compact />
    </template>
  </div>
</template>
<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { usePersonStore } from '@/stores/personStore'
import { expenseApi } from '@/api/expenseApi'
import type { TravelExpense } from '@/types'
import { fmtEur } from '@/utils/formatting'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingState from '@/components/ui/LoadingState.vue'

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

const fmt = fmtEur

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
