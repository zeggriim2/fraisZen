<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-semibold text-gray-900">Récapitulatif fiscal</h2>
      <div class="flex items-center gap-3">
        <select v-model="selectedYear" class="rounded-lg border-gray-300 shadow-sm text-sm">
          <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>
    </div>

    <div v-if="!personStore.activePerson" class="bg-amber-50 border border-amber-200 rounded-xl p-5 text-sm text-amber-700">
      Sélectionnez une personne dans le menu latéral.
    </div>

    <div v-else-if="loading" class="flex justify-center py-24">
      <div class="w-8 h-8 border-4 border-indigo-200 border-t-indigo-600 rounded-full animate-spin"></div>
    </div>

    <template v-else-if="summary">
      <div class="bg-indigo-600 rounded-2xl p-6 text-white mb-6 shadow-lg">
        <p class="text-indigo-200 text-sm font-medium">Total déductible {{ summary.year }}</p>
        <p class="text-5xl font-bold mt-2">{{ fmt(summary.total) }}</p>
        <p class="text-indigo-200 text-sm mt-2">{{ personStore.activePerson?.fullName }}</p>
      </div>

      <div class="grid grid-cols-3 gap-5 mb-8">
        <SummaryCard icon="🚗" title="Trajets" subtitle="Barème kilométrique 2024" :amount="summary.travel.deduction" color="blue">
          <template #details>
            <div class="flex justify-between text-sm"><span class="text-gray-500">Trajets</span><span class="font-medium">{{ summary.travel.trips.length }}</span></div>
            <div class="flex justify-between text-sm"><span class="text-gray-500">Distance totale</span><span class="font-medium">{{ summary.travel.totalKm.toFixed(0) }} km</span></div>
          </template>
        </SummaryCard>
        <SummaryCard icon="🏠" title="Télétravail" subtitle="2,50 € / jour" :amount="summary.remoteWork.deduction" color="emerald">
          <template #details>
            <div class="flex justify-between text-sm"><span class="text-gray-500">Jours</span><span class="font-medium">{{ summary.remoteWork.days }}</span></div>
            <div class="flex justify-between text-sm"><span class="text-gray-500">Indemnité/jour</span><span class="font-medium">2,50 €</span></div>
          </template>
        </SummaryCard>
        <SummaryCard icon="🛣️" title="Péages" subtitle="Montant réel" :amount="summary.toll.deduction" color="amber">
          <template #details>
            <div class="flex justify-between text-sm"><span class="text-gray-500">Entrées</span><span class="font-medium">{{ summary.toll.entries }}</span></div>
          </template>
        </SummaryCard>
      </div>

      <div v-if="summary.travel.trips.length" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 font-semibold text-gray-900">Détail des trajets</div>
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
      </div>

      <p class="mt-4 text-xs text-gray-400 text-center">
        Trajets calculés selon le barème kilométrique officiel 2024 en tenant compte du kilométrage annuel cumulé par CV fiscal.
      </p>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, defineComponent, h } from 'vue'
import { usePersonStore } from '@/stores/personStore'
import { useExpenseStore } from '@/stores/expenseStore'
import type { ExpenseSummary } from '@/types'

const personStore = usePersonStore()
const expenseStore = useExpenseStore()
const now = new Date().getFullYear()
const selectedYear = ref(now)
const years = Array.from({ length: 5 }, (_, i) => now - i)
const loading = ref(false)
const summary = ref<ExpenseSummary | null>(null)

const fmt = (v: number) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(v)

// Inline SummaryCard component to avoid extra file
const SummaryCard = defineComponent({
  props: { icon: String, title: String, subtitle: String, amount: Number, color: String },
  setup(props, { slots }) {
    const colors: Record<string, { bg: string; ring: string; icon: string; text: string }> = {
      blue:    { bg: 'bg-blue-100',    ring: 'bg-blue-50 border-blue-100',    icon: 'text-blue-600',    text: 'text-blue-600' },
      emerald: { bg: 'bg-emerald-100', ring: 'bg-emerald-50 border-emerald-100', icon: 'text-emerald-600', text: 'text-emerald-600' },
      amber:   { bg: 'bg-amber-100',   ring: 'bg-amber-50 border-amber-100',   icon: 'text-amber-600',   text: 'text-amber-600' },
    }
    const c = colors[props.color ?? 'blue']
    return () => h('div', { class: `bg-white rounded-2xl border border-gray-200 p-5 shadow-sm` }, [
      h('div', { class: 'flex items-center gap-3 mb-4' }, [
        h('div', { class: `w-10 h-10 rounded-xl ${c.bg} flex items-center justify-center text-xl` }, props.icon),
        h('div', {}, [h('p', { class: 'font-semibold text-gray-900' }, props.title), h('p', { class: 'text-xs text-gray-500' }, props.subtitle)]),
      ]),
      h('div', { class: 'space-y-2' }, [slots.details?.()]),
      h('div', { class: 'mt-4 pt-4 border-t border-gray-100 flex justify-between items-center' }, [
        h('span', { class: 'text-sm text-gray-500' }, 'Déduction'),
        h('span', { class: `text-lg font-bold ${c.text}` }, fmt(props.amount ?? 0)),
      ]),
    ])
  },
})

async function load() {
  if (!personStore.activePerson) return
  loading.value = true; summary.value = null
  try {
    await expenseStore.fetchSummary(personStore.activePerson.id, selectedYear.value)
    summary.value = expenseStore.summary
  } finally { loading.value = false }
}

watch(() => personStore.activePerson, load)
watch(selectedYear, load)
onMounted(load)
</script>
