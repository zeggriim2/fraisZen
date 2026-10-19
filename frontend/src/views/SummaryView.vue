<template>
  <div class="workspace-page">
    <PageHeader eyebrow="Votre bilan annuel" title="Chaque frais compte." description="Récapitulatif fiscal de l’année, prêt à être exporté.">
      <template #actions>
        <div class="form-field">
          <label for="summary-year" class="sr-only">Année fiscale</label>
          <select id="summary-year" v-model="selectedYear" class="form-control w-auto">
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
        <template v-if="summary && personStore.activePerson">
          <BaseButton variant="secondary" icon="file-spreadsheet" :loading="csvLoading" :disabled="!declarationReady" @click="downloadCsv">{{ csvLoading ? 'Génération…' : 'CSV' }}</BaseButton>
          <BaseButton variant="brand-soft" icon="file-text" :loading="pdfLoading" :disabled="!declarationReady" @click="downloadPdf">{{ pdfLoading ? 'Génération…' : 'PDF' }}</BaseButton>
        </template>
      </template>
    </PageHeader>

    <EmptyState v-if="!personStore.activePerson" icon="chart" title="Aucun profil sélectionné" description="Sélectionnez une personne dans le menu latéral pour afficher son récapitulatif fiscal.">
      <template #actions><BaseButton to="/persons" variant="secondary">Gérer les profils</BaseButton></template>
    </EmptyState>

    <LoadingState v-else-if="loading" label="Calcul du récapitulatif…" />

    <template v-else-if="summary">
      <div class="report-overview">
        <section class="report-total"><span>FRAIS DÉDUCTIBLES · {{ summary.year }}</span><strong>{{ fmt(summary.total) }}</strong><p>{{ personStore.activePerson?.fullName }}</p><footer><span>{{ summary.travel.totalKm.toFixed(0) }} km parcourus</span><span>{{ summary.remoteWork.days }} jours de télétravail</span></footer></section>
        <section class="report-breakdown"><h3>La répartition de vos frais</h3><div class="distribution-bar" role="img" aria-label="Répartition des déductions par catégorie"><span v-for="item in distribution" :key="item.label" :class="item.color" :style="{width: `${item.percent}%`}" /></div><p v-if="!summary.total" class="text-sm text-ink-muted mb-4">Vos premiers frais apparaîtront ici.</p><div class="distribution-legend"><div v-for="item in distribution" :key="item.label"><span :class="['category-dot',item.color]" /><span>{{ item.label }}</span><strong>{{ fmt(item.amount) }}</strong></div></div></section>
      </div>

      <!-- Comparaison forfait 10% -->
      <section class="card p-5 mb-6" aria-labelledby="forfait-title">
        <h3 id="forfait-title" class="text-base font-semibold text-ink mb-3">Comparaison avec le forfait 10 %</h3>
        <div class="flex flex-wrap items-end gap-3">
          <FormField label="Salaire brut annuel" field-id="gross-salary" hint="Renseignez votre salaire pour comparer" class="w-full sm:w-56" v-slot="{ id, describedBy }">
            <input :id="id" v-model.number="grossSalary" type="number" min="0" step="100" inputmode="numeric" placeholder="ex : 35 000" class="form-control" :aria-describedby="describedBy" />
          </FormField>
          <div v-if="grossSalary > 0" :class="['alert flex-1 min-w-0 self-end', forfaitComparison.favorable ? 'alert-success' : 'alert-warning']" role="status">
            <AppIcon :name="forfaitComparison.favorable ? 'check-circle' : 'info'" :size="18" />
            <span class="font-medium">{{ forfaitComparison.label }}</span>
          </div>
        </div>
      </section>

      <!-- Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 mb-8">
        <SummaryCard icon="car" title="Trajets" subtitle="Barème kilométrique" :amount="summary.travel.deduction" color="blue">
          <template #details>
            <div class="flex justify-between text-sm"><span class="text-ink-muted">Trajets</span><span class="font-medium">{{ summary.travel.trips.length }}</span></div>
            <div class="flex justify-between text-sm"><span class="text-ink-muted">Distance</span><span class="font-medium">{{ summary.travel.totalKm.toFixed(0) }} km</span></div>
          </template>
        </SummaryCard>
        <SummaryCard icon="home" title="Télétravail" :subtitle="`${summary.remoteWork.dailyAllowance.toFixed(2)} € / jour`" :amount="summary.remoteWork.deduction" color="emerald">
          <template #details>
            <div class="flex justify-between text-sm"><span class="text-ink-muted">Jours</span><span class="font-medium">{{ summary.remoteWork.days }}</span></div>
          </template>
        </SummaryCard>
        <SummaryCard icon="road" title="Péages" subtitle="Montant réel" :amount="summary.toll.deduction" color="amber">
          <template #details>
            <div class="flex justify-between text-sm"><span class="text-ink-muted">Entrées</span><span class="font-medium">{{ summary.toll.entries }}</span></div>
          </template>
        </SummaryCard>
        <SummaryCard icon="utensils" title="Repas" :subtitle="`Montant réel − ${summary.meal.homeMealValue.toFixed(2)} €`" :amount="summary.meal.deduction" color="orange">
          <template #details>
            <div class="flex justify-between text-sm"><span class="text-ink-muted">Repas</span><span class="font-medium">{{ summary.meal.entries }}</span></div>
          </template>
        </SummaryCard>
        <SummaryCard icon="parking" title="Parking" subtitle="Montant réel" :amount="summary.parking.deduction" color="rose">
          <template #details>
            <div class="flex justify-between text-sm"><span class="text-ink-muted">Entrées</span><span class="font-medium">{{ summary.parking.entries }}</span></div>
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
      <AlertBanner v-if="baremeError" variant="warning" class="mb-4">{{ baremeError }}</AlertBanner>

      <!-- Détail barème kilométrique -->
      <div v-if="summary.travel.trips.length && baremeYear" class="card overflow-hidden mb-4">
        <div class="px-5 py-4 border-b border-line flex items-center justify-between">
          <span class="font-semibold text-ink">Détail des trajets</span>
          <router-link :to="`/trips?year=${selectedYear}&personId=${personStore.activePerson?.id}`"
            class="btn-link text-xs">
            Voir tous les trajets <AppIcon name="arrow-right" :size="14" />
          </router-link>
        </div>
        <div class="px-5 py-4 bg-surface-muted">
          <p class="text-xs font-semibold text-ink-muted uppercase tracking-wider mb-3">Barème appliqué</p>
          <div v-for="(b, key) in baremeBreakdown" :key="key" class="mb-3">
            <p class="text-xs font-medium text-ink mb-1">{{ b.label }} — {{ b.totalKm.toFixed(0) }} km cumulés</p>
            <div class="space-y-0.5">
              <div v-for="tr in b.tranches" :key="tr.label" class="flex justify-between text-xs text-ink-muted">
                <span>{{ tr.label }}</span>
                <span class="font-medium text-ink">{{ fmt(tr.amount) }}</span>
              </div>
            </div>
            <div class="flex justify-between text-xs font-semibold text-brand-700 mt-1 pt-1 border-t border-line">
              <span>Sous-total {{ b.label }}</span>
              <span>{{ fmt(b.subtotal) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Multi-années -->
      <div v-if="multiYearData.some(y => y)" class="card overflow-x-auto">
        <div class="px-5 py-4 border-b border-line font-semibold text-ink">Évolution pluriannuelle</div>
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-line bg-surface-muted">
              <th class="px-5 py-2 text-left text-xs font-semibold text-ink-muted">Année</th>
              <th class="px-3 py-2 text-right text-xs font-semibold text-ink-muted">Trajets</th>
              <th class="px-3 py-2 text-right text-xs font-semibold text-ink-muted">Télétravail</th>
              <th class="px-3 py-2 text-right text-xs font-semibold text-ink-muted">Péages</th>
              <th class="px-3 py-2 text-right text-xs font-semibold text-ink-muted">Repas</th>
              <th class="px-3 py-2 text-right text-xs font-semibold text-ink-muted">Parking</th>
              <th class="px-5 py-2 text-right text-xs font-semibold text-ink-muted">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, i) in multiYearData" :key="i"
              :class="['border-b border-gray-50', row?.year === selectedYear ? 'bg-brand-50' : '']">
              <template v-if="row">
                <td class="px-5 py-2.5 font-medium" :class="row.year === selectedYear ? 'text-brand-700' : 'text-ink'">{{ row.year }}</td>
                <td class="px-3 py-2.5 text-right text-ink-muted">{{ fmt(row.travel.deduction) }}</td>
                <td class="px-3 py-2.5 text-right text-ink-muted">{{ fmt(row.remoteWork.deduction) }}</td>
                <td class="px-3 py-2.5 text-right text-ink-muted">{{ fmt(row.toll.deduction) }}</td>
                <td class="px-3 py-2.5 text-right text-ink-muted">{{ fmt(row.meal?.deduction ?? 0) }}</td>
                <td class="px-3 py-2.5 text-right text-ink-muted">{{ fmt(row.parking?.deduction ?? 0) }}</td>
                <td class="px-5 py-2.5 text-right font-semibold" :class="row.year === selectedYear ? 'text-brand-700' : 'text-ink'">{{ fmt(row.total) }}</td>
              </template>
              <template v-else>
                <td class="px-5 py-2.5 text-ink-subtle">{{ multiYears[i] }}</td>
                <td colspan="5" class="px-3 py-2.5 text-ink-subtle text-center text-xs">Aucune donnée</td>
              </template>
            </tr>
          </tbody>
        </table>
      </div>

      <p class="mt-4 text-xs text-ink-subtle text-center">
        Trajets calculés selon le barème kilométrique officiel {{ selectedYear }} en tenant compte du kilométrage annuel cumulé par CV fiscal.
      </p>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import SummaryCard from '@/components/ui/SummaryCard.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import AlertBanner from '@/components/ui/AlertBanner.vue'
import FormField from '@/components/ui/FormField.vue'
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
const route = useRoute()
const _now = new Date()
const now = _now.getFullYear()
// En période de déclaration (jan–juin), on affiche par défaut l'année N-1 (revenus à déclarer).
// De juillet à décembre, l'utilisateur saisit ses dépenses courantes → année N.
const declarationDefaultYear = _now.getMonth() < 6 ? now - 1 : now
// Deep link : /summary?year=2025 (ex. depuis le calendrier) prime sur la préférence utilisateur.
const queryYear = Number.parseInt(String(route.query.year ?? ''), 10)
const selectedYear = ref(Number.isFinite(queryYear) && queryYear > 2000 ? queryYear : (authStore.user?.defaultYear ?? declarationDefaultYear))
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
