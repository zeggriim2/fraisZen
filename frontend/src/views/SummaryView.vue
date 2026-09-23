<template>
  <div class="workspace-page">
    <div class="workspace-title">
      <div><span class="eyebrow">VOTRE BILAN ANNUEL</span><h2>Chaque frais compte.</h2><p>Votre récapitulatif fiscal, prêt à être exporté.</p></div>
      <div class="flex items-center gap-3">
        <select v-model="selectedYear" class="rounded-lg border-gray-300 shadow-sm text-sm">
          <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
        </select>
        <template v-if="summary && personStore.activePerson">
          <button @click="downloadCsv" :disabled="!declarationReady || csvLoading"
            class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors">
            <AppIcon name="chart" />{{ csvLoading ? 'Génération…' : 'CSV' }}
          </button>
          <button @click="downloadPdf" :disabled="!declarationReady || pdfLoading"
            class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-50 disabled:opacity-50 transition-colors">
            <AppIcon name="receipt" />{{ pdfLoading ? 'Génération…' : 'PDF' }}
          </button>
        </template>
      </div>
    </div>

    <div v-if="!personStore.activePerson" class="flex flex-col items-center justify-center py-20 text-center">
      <div class="w-16 h-16 rounded-full bg-indigo-50 flex items-center justify-center mb-4">
        <svg class="w-8 h-8 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
        </svg>
      </div>
      <h3 class="text-base font-semibold text-gray-900 mb-1">Aucun profil sélectionné</h3>
      <p class="text-sm text-gray-500">Sélectionnez une personne dans le menu latéral pour afficher son récapitulatif fiscal.</p>
    </div>

    <div v-else-if="loading" class="flex justify-center py-24">
      <div class="w-8 h-8 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin"></div>
    </div>

    <template v-else-if="summary">
      <div class="report-overview">
        <section class="report-total"><span>FRAIS DÉDUCTIBLES · {{ summary.year }}</span><strong>{{ fmt(summary.total) }}</strong><p>{{ personStore.activePerson?.fullName }}</p><footer><span>{{ summary.travel.totalKm.toFixed(0) }} km parcourus</span><span>{{ summary.remoteWork.days }} jours de télétravail</span></footer></section>
        <section class="report-breakdown"><h3>La répartition de vos frais</h3><div class="distribution-bar" role="img" aria-label="Répartition des déductions par catégorie"><span v-for="item in distribution" :key="item.label" :class="item.color" :style="{width: `${item.percent}%`}" /></div><p v-if="!summary.total" class="text-sm text-gray-500 mb-4">Vos premiers frais apparaîtront ici.</p><div class="distribution-legend"><div v-for="item in distribution" :key="item.label"><span :class="['category-dot',item.color]" /><span>{{ item.label }}</span><strong>{{ fmt(item.amount) }}</strong></div></div></section>
      </div>

      <!-- Comparaison forfait 10% -->
      <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 mb-6">
        <p class="text-sm font-semibold text-gray-700 mb-3">Comparaison avec le forfait 10 %</p>
        <div class="flex flex-wrap items-center gap-3">
          <label class="text-sm text-gray-500 whitespace-nowrap">Salaire brut annuel :</label>
          <input v-model.number="grossSalary" type="number" min="0" step="100" placeholder="ex : 35 000"
            class="w-40 px-3 py-1.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300" />
          <span v-if="grossSalary > 0" :class="['text-sm font-medium px-3 py-1.5 rounded-lg', forfaitComparison.favorable ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200']">
            {{ forfaitComparison.label }}
          </span>
          <span v-else class="text-xs text-gray-400">Renseignez votre salaire pour comparer</span>
        </div>
      </div>

      <!-- Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 mb-8">
        <SummaryCard icon="🚗" title="Trajets" subtitle="Barème kilométrique" :amount="summary.travel.deduction" color="blue">
          <template #details>
            <div class="flex justify-between text-sm"><span class="text-gray-500">Trajets</span><span class="font-medium">{{ summary.travel.trips.length }}</span></div>
            <div class="flex justify-between text-sm"><span class="text-gray-500">Distance</span><span class="font-medium">{{ summary.travel.totalKm.toFixed(0) }} km</span></div>
          </template>
        </SummaryCard>
        <SummaryCard icon="🏠" title="Télétravail" :subtitle="`${summary.remoteWork.dailyAllowance.toFixed(2)} € / jour`" :amount="summary.remoteWork.deduction" color="emerald">
          <template #details>
            <div class="flex justify-between text-sm"><span class="text-gray-500">Jours</span><span class="font-medium">{{ summary.remoteWork.days }}</span></div>
          </template>
        </SummaryCard>
        <SummaryCard icon="🛣️" title="Péages" subtitle="Montant réel" :amount="summary.toll.deduction" color="amber">
          <template #details>
            <div class="flex justify-between text-sm"><span class="text-gray-500">Entrées</span><span class="font-medium">{{ summary.toll.entries }}</span></div>
          </template>
        </SummaryCard>
        <SummaryCard icon="🍽️" title="Repas" :subtitle="`Montant réel − ${summary.meal.homeMealValue.toFixed(2)} €`" :amount="summary.meal.deduction" color="orange">
          <template #details>
            <div class="flex justify-between text-sm"><span class="text-gray-500">Repas</span><span class="font-medium">{{ summary.meal.entries }}</span></div>
          </template>
        </SummaryCard>
        <SummaryCard icon="🅿️" title="Parking" subtitle="Montant réel" :amount="summary.parking.deduction" color="rose">
          <template #details>
            <div class="flex justify-between text-sm"><span class="text-gray-500">Entrées</span><span class="font-medium">{{ summary.parking.entries }}</span></div>
          </template>
        </SummaryCard>
      </div>

      <DeclarationAssistant
        v-model:tax-case="taxCase"
        :person-name="personStore.activePerson?.fullName ?? ''"
        :year="summary.year"
        :total="summary.total"
        :ready="declarationReady"
        :missing="declarationMissing"
        :pdf-loading="pdfLoading"
        :csv-loading="csvLoading"
        @download-pdf="downloadPdf"
        @download-csv="downloadCsv"
      />

      <!-- Erreur barème kilométrique -->
      <div v-if="baremeError" class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4 flex items-start gap-3">
        <span class="text-amber-500 text-lg shrink-0">⚠️</span>
        <p class="text-sm text-amber-700">{{ baremeError }}</p>
      </div>

      <!-- Détail barème kilométrique -->
      <div v-if="summary.travel.trips.length && baremeYear" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-4">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <span class="font-semibold text-gray-900">Détail des trajets</span>
          <router-link :to="`/trips?year=${selectedYear}&personId=${personStore.activePerson?.id}`"
            class="text-xs font-medium text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
            Voir tous les trajets <span>→</span>
          </router-link>
        </div>
        <div class="px-5 py-4 bg-gray-50">
          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Barème appliqué</p>
          <div v-for="(b, key) in baremeBreakdown" :key="key" class="mb-3">
            <p class="text-xs font-medium text-gray-700 mb-1">{{ b.label }} — {{ b.totalKm.toFixed(0) }} km cumulés</p>
            <div class="space-y-0.5">
              <div v-for="tr in b.tranches" :key="tr.label" class="flex justify-between text-xs text-gray-600">
                <span>{{ tr.label }}</span>
                <span class="font-medium text-gray-800">{{ fmt(tr.amount) }}</span>
              </div>
            </div>
            <div class="flex justify-between text-xs font-semibold text-indigo-600 mt-1 pt-1 border-t border-gray-200">
              <span>Sous-total {{ b.label }}</span>
              <span>{{ fmt(b.subtotal) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Multi-années -->
      <div v-if="multiYearData.some(y => y)" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-x-auto">
        <div class="px-5 py-4 border-b border-gray-100 font-semibold text-gray-900">Évolution pluriannuelle</div>
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100 bg-gray-50">
              <th class="px-5 py-2 text-left text-xs font-semibold text-gray-500">Année</th>
              <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500">Trajets</th>
              <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500">Télétravail</th>
              <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500">Péages</th>
              <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500">Repas</th>
              <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500">Parking</th>
              <th class="px-5 py-2 text-right text-xs font-semibold text-gray-500">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, i) in multiYearData" :key="i"
              :class="['border-b border-gray-50', row?.year === selectedYear ? 'bg-indigo-50' : '']">
              <template v-if="row">
                <td class="px-5 py-2.5 font-medium" :class="row.year === selectedYear ? 'text-indigo-700' : 'text-gray-900'">{{ row.year }}</td>
                <td class="px-3 py-2.5 text-right text-gray-600">{{ fmt(row.travel.deduction) }}</td>
                <td class="px-3 py-2.5 text-right text-gray-600">{{ fmt(row.remoteWork.deduction) }}</td>
                <td class="px-3 py-2.5 text-right text-gray-600">{{ fmt(row.toll.deduction) }}</td>
                <td class="px-3 py-2.5 text-right text-gray-600">{{ fmt(row.meal?.deduction ?? 0) }}</td>
                <td class="px-3 py-2.5 text-right text-gray-600">{{ fmt(row.parking?.deduction ?? 0) }}</td>
                <td class="px-5 py-2.5 text-right font-semibold" :class="row.year === selectedYear ? 'text-indigo-700' : 'text-gray-900'">{{ fmt(row.total) }}</td>
              </template>
              <template v-else>
                <td class="px-5 py-2.5 text-gray-400">{{ multiYears[i] }}</td>
                <td colspan="5" class="px-3 py-2.5 text-gray-400 text-center text-xs">Aucune donnée</td>
              </template>
            </tr>
          </tbody>
        </table>
      </div>

      <p class="mt-4 text-xs text-gray-400 text-center">
        Trajets calculés selon le barème kilométrique officiel {{ selectedYear }} en tenant compte du kilométrage annuel cumulé par CV fiscal.
      </p>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, computed } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import SummaryCard from '@/components/ui/SummaryCard.vue'
import DeclarationAssistant from '@/components/expense/DeclarationAssistant.vue'
import { usePersonStore } from '@/stores/personStore'
import { useExpenseStore } from '@/stores/expenseStore'
import { useAuthStore } from '@/stores/authStore'
import { expenseApi, type BaremeYear, type TrancheTaux } from '@/api/expenseApi'
import type { ExpenseSummary } from '@/types'
import { fmtEur } from '@/utils/formatting'

const personStore = usePersonStore()
const expenseStore = useExpenseStore()
const authStore = useAuthStore()
const _now = new Date()
const now = _now.getFullYear()
// En période de déclaration (jan–juin), on affiche par défaut l'année N-1 (revenus à déclarer).
// De juillet à décembre, l'utilisateur saisit ses dépenses courantes → année N.
const declarationDefaultYear = _now.getMonth() < 6 ? now - 1 : now
const selectedYear = ref(authStore.user?.defaultYear ?? declarationDefaultYear)
const years = Array.from({ length: 6 }, (_, i) => now - i)
const multiYears = Array.from({ length: 5 }, (_, i) => now - i)
const loading = ref(false)
const pdfLoading = ref(false)
const csvLoading = ref(false)
const summary = ref<ExpenseSummary | null>(null)
const multiYearData = ref<(ExpenseSummary | null)[]>([])
const grossSalary = ref<number>(parseInt(localStorage.getItem('grossSalary') ?? '0') || 0)
const taxCase = ref('1AK')

watch(grossSalary, v => localStorage.setItem('grossSalary', String(v)))
watch(taxCase, value => {
  if (personStore.activePerson) localStorage.setItem(`taxCase:${personStore.activePerson.id}`, value)
})

const declarationMissing = computed(() => {
  if (!summary.value) return ['Le récapitulatif annuel est indisponible.']
  const missing: string[] = []
  if (summary.value.year >= now) {
    missing.push(`L’année ${summary.value.year} doit être terminée. Ces frais seront déclarables pendant la campagne ${summary.value.year + 1}.`)
  }
  if (summary.value.total <= 0) missing.push('Ajoutez au moins une dépense déductible avant de générer le dossier.')
  if (summary.value.travel.trips.length > 0 && !baremeYear.value) missing.push(`Le barème kilométrique ${selectedYear.value} doit être configuré.`)
  return missing
})
const declarationReady = computed(() => declarationMissing.value.length === 0)

const distribution = computed(() => {
  const s = summary.value
  if (!s) return []
  return [
    { label: 'Trajets', amount: s.travel.deduction, color: 'bg-blue-500' },
    { label: 'Télétravail', amount: s.remoteWork.deduction, color: 'bg-emerald-500' },
    { label: 'Péages', amount: s.toll.deduction, color: 'bg-amber-500' },
    { label: 'Repas', amount: s.meal.deduction, color: 'bg-orange-500' },
    { label: 'Parking', amount: s.parking.deduction, color: 'bg-rose-500' },
  ].map(item => ({ ...item, percent: s.total > 0 ? item.amount / s.total * 100 : 0 }))
})
const fmt = fmtEur

// B — Comparaison forfait 10%
const FORFAIT_CAP_2024 = 14171
const forfaitComparison = computed(() => {
  if (!summary.value || grossSalary.value <= 0) return { favorable: true, label: '' }
  const forfait = Math.min(grossSalary.value * 0.10, FORFAIT_CAP_2024)
  const diff = summary.value.total - forfait
  if (diff > 0) return { favorable: true, label: `Frais réels avantageux : +${fmt(diff)} vs forfait 10 %` }
  return { favorable: false, label: `Forfait 10 % plus avantageux de ${fmt(Math.abs(diff))}` }
})

// E — Détail barème (taux chargés depuis l'API pour refléter la configuration BDD)
const baremeYear = ref<BaremeYear | null>(null)
const baremeError = ref<string | null>(null)

function applyTranches(b: TrancheTaux, km: number, t1 = 5000, t2 = 20000) {
  if (km <= t1) return [{ label: `${km.toFixed(0)} km × ${b.rate1} €/km (tranche 1)`, amount: km * b.rate1 }]
  if (km <= t2) return [{ label: `${km.toFixed(0)} km × ${b.rate2} + ${b.fixed2} € (tranche 2)`, amount: km * b.rate2 + b.fixed2 }]
  return [{ label: `${km.toFixed(0)} km × ${b.rate3} €/km (tranche 3)`, amount: km * b.rate3 }]
}

const baremeBreakdown = computed(() => {
  if (!summary.value || !baremeYear.value) return {}
  const rates = baremeYear.value.rates
  const buckets: Record<string, { label: string; totalKm: number; tranches: { label: string; amount: number }[]; subtotal: number }> = {}

  for (const t of summary.value.travel.trips) {
    const cv = Math.min(Math.max(t.vehiclePower ?? 5, 3), 7)
    const key = `${t.vehicleType}|${cv}|${t.isElectric ? 1 : 0}`
    if (!buckets[key]) {
      const vehicleLabel = t.vehicleType === 'car' ? `Voiture ${cv} CV${t.isElectric ? ' (électrique)' : ''}` : `${t.vehicleType} ${cv} CV`
      buckets[key] = { label: vehicleLabel, totalKm: 0, tranches: [], subtotal: 0 }
    }
    buckets[key].totalKm += t.distanceKm
  }

  for (const [key, b] of Object.entries(buckets)) {
    const [vehicleType, cvStr] = key.split('|')
    const cv = parseInt(cvStr)
    if (vehicleType === 'car') {
      const r = rates.car[cv] ?? rates.car[5]
      b.tranches = applyTranches(r, b.totalKm, 5000, 20000)
      b.subtotal = b.tranches.reduce((s, t) => s + t.amount, 0)
      if (b.label.includes('électrique')) b.subtotal = Math.round(b.subtotal * rates.electricMultiplier * 100) / 100
    } else if (vehicleType === 'motorcycle') {
      const group = cv <= 2 ? 1 : cv <= 5 ? 3 : 6
      const r = rates.motorcycle[group]
      b.tranches = applyTranches(r, b.totalKm, 3000, 6000)
      b.subtotal = b.tranches.reduce((s, t) => s + t.amount, 0)
    } else {
      b.tranches = applyTranches(rates.moped, b.totalKm, 3000, 6000)
      b.subtotal = b.tranches.reduce((s, t) => s + t.amount, 0)
    }
  }
  return buckets
})

async function load() {
  if (!personStore.activePerson) return
  taxCase.value = localStorage.getItem(`taxCase:${personStore.activePerson.id}`) ?? '1AK'
  loading.value = true; summary.value = null; baremeError.value = null; baremeYear.value = null
  try {
    const [, bareme] = await Promise.all([
      expenseStore.fetchSummary(personStore.activePerson.id, selectedYear.value),
      expenseApi.getBareme(selectedYear.value).catch((err: unknown) => {
        const msg = (err as { response?: { data?: { error?: string } } })?.response?.data?.error
        baremeError.value = msg ?? `Le barème kilométrique de ${selectedYear.value} n'a pas été configuré.`
        return null
      }),
    ])
    summary.value = expenseStore.summary
    baremeYear.value = bareme
    loadMultiYear()
  } finally { loading.value = false }
}

// H — Multi-années en parallèle
async function loadMultiYear() {
  if (!personStore.activePerson) return
  const pid = personStore.activePerson.id
  const results = await Promise.all(
    multiYears.map(y => expenseApi.getSummary(pid, y).catch(() => null))
  )
  multiYearData.value = results
}

// A — CSV
async function downloadCsv() {
  if (!personStore.activePerson) return
  csvLoading.value = true
  try {
    const blob = await expenseApi.downloadCsv(personStore.activePerson.id, selectedYear.value, taxCase.value)
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url; a.download = `frais-reels-${selectedYear.value}.csv`; a.click()
    URL.revokeObjectURL(url)
  } finally { csvLoading.value = false }
}

// A — PDF
async function downloadPdf() {
  if (!personStore.activePerson) return
  pdfLoading.value = true
  try {
    const blob = await expenseApi.downloadPdf(personStore.activePerson.id, selectedYear.value, taxCase.value)
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url; a.download = `frais-reels-${selectedYear.value}.pdf`; a.click()
    URL.revokeObjectURL(url)
  } finally { pdfLoading.value = false }
}

watch(() => personStore.activePerson, load)
watch(selectedYear, load)
onMounted(load)
</script>
