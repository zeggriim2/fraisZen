<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-semibold text-gray-900">Récapitulatif fiscal</h2>
      <div class="flex items-center gap-3">
        <select v-model="selectedYear" class="rounded-lg border-gray-300 shadow-sm text-sm">
          <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
        </select>
        <template v-if="summary && personStore.activePerson">
          <button @click="downloadCsv" :disabled="csvLoading"
            class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 disabled:opacity-50 transition-colors">
            <span>📊</span>{{ csvLoading ? 'Génération…' : 'CSV' }}
          </button>
          <button @click="downloadPdf" :disabled="pdfLoading"
            class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-50 disabled:opacity-50 transition-colors">
            <span>📄</span>{{ pdfLoading ? 'Génération…' : 'PDF' }}
          </button>
        </template>
      </div>
    </div>

    <div v-if="!personStore.activePerson" class="bg-amber-50 border border-amber-200 rounded-xl p-5 text-sm text-amber-700">
      Sélectionnez une personne dans le menu latéral.
    </div>

    <div v-else-if="loading" class="flex justify-center py-24">
      <div class="w-8 h-8 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin"></div>
    </div>

    <template v-else-if="summary">
      <!-- Total hero -->
      <div class="bg-indigo-600 rounded-2xl p-6 text-white mb-4 shadow-lg">
        <p class="text-indigo-200 text-sm font-medium">Total déductible {{ summary.year }}</p>
        <p class="text-5xl font-bold mt-2">{{ fmt(summary.total) }}</p>
        <p class="text-indigo-200 text-sm mt-2">{{ personStore.activePerson?.fullName }}</p>
      </div>

      <!-- Comparaison forfait 10% -->
      <div class="mb-6">
        <div class="flex items-center gap-3">
          <label class="text-sm text-gray-500 whitespace-nowrap">Salaire brut annuel :</label>
          <input v-model.number="grossSalary" type="number" min="0" step="100" placeholder="ex : 35000"
            class="w-40 px-3 py-1.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300" />
          <span v-if="grossSalary > 0" :class="['text-sm font-medium px-3 py-1.5 rounded-lg', forfaitComparison.favorable ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700']">
            {{ forfaitComparison.label }}
          </span>
        </div>
      </div>

      <!-- Cards -->
      <div class="grid grid-cols-4 gap-4 mb-8">
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
        <SummaryCard icon="🍽️" title="Repas" subtitle="Montant réel − 5,35 €" :amount="summary.meal.deduction" color="orange">
          <template #details>
            <div class="flex justify-between text-sm"><span class="text-gray-500">Repas</span><span class="font-medium">{{ summary.meal.entries }}</span></div>
          </template>
        </SummaryCard>
      </div>

      <!-- Guide Cerfa -->
      <div class="bg-white rounded-2xl border border-emerald-200 shadow-sm p-5 mb-4">
        <div class="flex items-start gap-3">
          <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center text-xl shrink-0">🧾</div>
          <div class="flex-1">
            <p class="font-semibold text-gray-900 text-sm mb-1">Aide au remplissage — Déclaration {{ summary.year }}</p>
            <p class="text-xs text-gray-500 mb-3">Reportez les montants suivants dans votre déclaration de revenus 2042.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div class="bg-emerald-50 rounded-xl p-3 border border-emerald-100">
                <p class="text-xs font-semibold text-emerald-700 uppercase tracking-wide mb-1">Déclarant 1 — Case 1AK</p>
                <p class="text-xl font-bold text-emerald-700">{{ fmt(summary.total) }}</p>
                <p class="text-xs text-gray-500 mt-1">Frais réels déductibles (remplace l'abattement 10 %)</p>
              </div>
              <div class="bg-gray-50 rounded-xl p-3 border border-gray-200">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Déclarant 2 — Case 1BK</p>
                <p class="text-sm text-gray-400 italic mt-2">Reporter le montant du second déclarant</p>
                <p class="text-xs text-gray-400 mt-1">Si foyer fiscal avec conjoint/partenaire</p>
              </div>
            </div>
            <p class="text-xs text-amber-600 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2 mt-3">
              Conservez tous vos justificatifs (relevés kilométriques, tickets de péage, notes de repas) en cas de contrôle fiscal.
            </p>
          </div>
        </div>
      </div>

      <!-- Détail des trajets -->
      <div v-if="summary.travel.trips.length" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden mb-4">
        <div class="px-5 py-4 border-b border-gray-100 font-semibold text-gray-900 flex items-center justify-between">
          <span>Détail des trajets</span>
          <button @click="showBareme = !showBareme" class="text-xs text-indigo-600 hover:underline">
            {{ showBareme ? 'Masquer le barème' : 'Voir le détail du barème' }}
          </button>
        </div>
        <div class="divide-y divide-gray-100">
          <div v-for="(t, i) in summary.travel.trips" :key="i" class="px-5 py-3 flex items-center justify-between text-sm">
            <div class="flex items-center gap-4">
              <span class="text-gray-400 text-xs w-24 shrink-0">{{ t.date }}</span>
              <span class="text-gray-700">{{ t.departure && t.arrival ? `${t.departure} → ${t.arrival}` : t.description ?? '—' }}</span>
            </div>
            <div class="flex items-center gap-2 shrink-0 ml-4">
              <span v-if="t.roundTrip" class="text-xs font-medium bg-blue-50 text-blue-600 px-2 py-0.5 rounded-full">A/R</span>
              <span class="text-gray-500">{{ t.distanceKm }} km · {{ t.vehiclePower }} CV</span>
            </div>
          </div>
        </div>

        <!-- Barème détail -->
        <div v-if="showBareme" class="px-5 py-4 bg-gray-50 border-t border-gray-100">
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
      <div v-if="multiYearData.some(y => y)" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 font-semibold text-gray-900">Évolution pluriannuelle</div>
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-100 bg-gray-50">
              <th class="px-5 py-2 text-left text-xs font-semibold text-gray-500">Année</th>
              <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500">Trajets</th>
              <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500">Télétravail</th>
              <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500">Péages</th>
              <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500">Repas</th>
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
import { ref, watch, onMounted, computed, defineComponent, h } from 'vue'
import { usePersonStore } from '@/stores/personStore'
import { useExpenseStore } from '@/stores/expenseStore'
import { useAuthStore } from '@/stores/authStore'
import { expenseApi } from '@/api/expenseApi'
import type { ExpenseSummary } from '@/types'

const personStore = usePersonStore()
const expenseStore = useExpenseStore()
const authStore = useAuthStore()
const now = new Date().getFullYear()
const selectedYear = ref(authStore.user?.defaultYear ?? now)
const years = Array.from({ length: 6 }, (_, i) => now - i)
const multiYears = Array.from({ length: 5 }, (_, i) => now - i)
const loading = ref(false)
const pdfLoading = ref(false)
const csvLoading = ref(false)
const summary = ref<ExpenseSummary | null>(null)
const multiYearData = ref<(ExpenseSummary | null)[]>([])
const showBareme = ref(false)
const grossSalary = ref<number>(parseInt(localStorage.getItem('grossSalary') ?? '0') || 0)

watch(grossSalary, v => localStorage.setItem('grossSalary', String(v)))

const fmt = (v: number) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(v)

// B — Comparaison forfait 10%
const FORFAIT_CAP_2024 = 14171
const forfaitComparison = computed(() => {
  if (!summary.value || grossSalary.value <= 0) return { favorable: true, label: '' }
  const forfait = Math.min(grossSalary.value * 0.10, FORFAIT_CAP_2024)
  const diff = summary.value.total - forfait
  if (diff > 0) return { favorable: true, label: `Frais réels avantageux : +${fmt(diff)} vs forfait 10 %` }
  return { favorable: false, label: `Forfait 10 % plus avantageux de ${fmt(Math.abs(diff))}` }
})

// E — Détail barème (calculé côté frontend à partir des trips)
const BAREME_2024: Record<number, { rate1: number; rate2: number; fixed2: number; rate3: number }> = {
  3: { rate1: 0.456, rate2: 0.273, fixed2: 915, rate3: 0.318 },
  4: { rate1: 0.523, rate2: 0.294, fixed2: 1147, rate3: 0.352 },
  5: { rate1: 0.548, rate2: 0.308, fixed2: 1200, rate3: 0.368 },
  6: { rate1: 0.574, rate2: 0.323, fixed2: 1256, rate3: 0.386 },
  7: { rate1: 0.601, rate2: 0.340, fixed2: 1301, rate3: 0.405 },
}

function applyTranches(b: { rate1: number; rate2: number; fixed2: number; rate3: number }, km: number) {
  const tranches = []
  if (km <= 5000) {
    tranches.push({ label: `${km.toFixed(0)} km × ${b.rate1} €/km (tranche 1)`, amount: km * b.rate1 })
  } else if (km <= 20000) {
    tranches.push({ label: `${km.toFixed(0)} km × ${b.rate2} + ${b.fixed2} € (tranche 2)`, amount: km * b.rate2 + b.fixed2 })
  } else {
    tranches.push({ label: `${km.toFixed(0)} km × ${b.rate3} €/km (tranche 3)`, amount: km * b.rate3 })
  }
  return tranches
}

const baremeBreakdown = computed(() => {
  if (!summary.value) return {}
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
  for (const b of Object.values(buckets)) {
    const cv = parseInt(b.label.match(/(\d+) CV/)?.[1] ?? '5')
    const rates = BAREME_2024[cv] ?? BAREME_2024[5]
    b.tranches = applyTranches(rates, b.totalKm)
    b.subtotal = b.tranches.reduce((s, t) => s + t.amount, 0)
    if (b.label.includes('électrique')) b.subtotal *= 1.20
  }
  return buckets
})

async function load() {
  if (!personStore.activePerson) return
  loading.value = true; summary.value = null
  try {
    await expenseStore.fetchSummary(personStore.activePerson.id, selectedYear.value)
    summary.value = expenseStore.summary
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
    const blob = await expenseApi.downloadCsv(personStore.activePerson.id, selectedYear.value)
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
    const blob = await expenseApi.downloadPdf(personStore.activePerson.id, selectedYear.value)
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url; a.download = `frais-reels-${selectedYear.value}.pdf`; a.click()
    URL.revokeObjectURL(url)
  } finally { pdfLoading.value = false }
}

watch(() => personStore.activePerson, load)
watch(selectedYear, load)
onMounted(load)

// Inline SummaryCard
const SummaryCard = defineComponent({
  props: { icon: String, title: String, subtitle: String, amount: Number, color: String },
  setup(props, { slots }) {
    const colors: Record<string, { bg: string; text: string }> = {
      blue:    { bg: 'bg-blue-100',    text: 'text-blue-600' },
      emerald: { bg: 'bg-emerald-100', text: 'text-emerald-600' },
      amber:   { bg: 'bg-amber-100',   text: 'text-amber-600' },
      orange:  { bg: 'bg-orange-100',  text: 'text-orange-600' },
    }
    const c = colors[props.color ?? 'blue']
    return () => h('div', { class: 'bg-white rounded-2xl border border-gray-200 p-5 shadow-sm' }, [
      h('div', { class: 'flex items-center gap-3 mb-4' }, [
        h('div', { class: `w-10 h-10 rounded-xl ${c.bg} flex items-center justify-center text-xl` }, props.icon),
        h('div', {}, [h('p', { class: 'font-semibold text-gray-900 text-sm' }, props.title), h('p', { class: 'text-xs text-gray-500' }, props.subtitle)]),
      ]),
      h('div', { class: 'space-y-2' }, [slots.details?.()]),
      h('div', { class: 'mt-4 pt-4 border-t border-gray-100 flex justify-between items-center' }, [
        h('span', { class: 'text-sm text-gray-500' }, 'Déduction'),
        h('span', { class: `text-lg font-bold ${c.text}` }, fmt(props.amount ?? 0)),
      ]),
    ])
  },
})
</script>
