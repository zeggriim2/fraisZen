<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-3">
        <button @click="prevMonth" class="p-2 rounded-lg hover:bg-gray-100">
          <svg class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <select v-model="month" class="text-base font-semibold text-gray-900 border-0 bg-transparent cursor-pointer focus:ring-0 capitalize">
          <option v-for="(name, i) in monthNames" :key="i" :value="i">{{ name }}</option>
        </select>
        <select v-model="year" class="text-base font-semibold text-gray-900 border-0 bg-transparent cursor-pointer focus:ring-0">
          <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
        </select>
        <button @click="nextMonth" class="p-2 rounded-lg hover:bg-gray-100">
          <svg class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>
        <button @click="goToday" class="px-3 py-1.5 text-sm font-medium text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-50">Aujourd'hui</button>
      </div>
      <span v-if="personStore.activePerson" class="text-sm text-gray-500">
        <span class="font-medium text-gray-900">{{ personStore.activePerson.fullName }}</span>
      </span>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-3 gap-4 mb-6">
      <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
        <p class="text-xs text-blue-600 font-medium">Trajets</p>
        <p class="text-2xl font-bold text-blue-700 mt-1">{{ stats.travelCount }}</p>
        <p class="text-xs text-blue-500 mt-1">{{ stats.travelKm.toFixed(0) }} km</p>
      </div>
      <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-4">
        <p class="text-xs text-emerald-600 font-medium">Télétravail</p>
        <p class="text-2xl font-bold text-emerald-700 mt-1">{{ stats.remoteCount }}</p>
        <p class="text-xs text-emerald-500 mt-1">{{ (stats.remoteCount * 2.5).toFixed(2) }} €</p>
      </div>
      <div class="bg-amber-50 border border-amber-100 rounded-xl p-4">
        <p class="text-xs text-amber-600 font-medium">Péages</p>
        <p class="text-2xl font-bold text-amber-700 mt-1">{{ stats.tollCount }}</p>
        <p class="text-xs text-amber-500 mt-1">{{ stats.tollAmount.toFixed(2) }} €</p>
      </div>
    </div>

    <!-- Calendar -->
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
      <div class="grid grid-cols-7 border-b border-gray-200">
        <div v-for="d in ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim']" :key="d"
          class="py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ d }}</div>
      </div>
      <div class="grid grid-cols-7">
        <div
          v-for="(cell, i) in cells" :key="i"
          @click="cell.date && openModal(cell.date)"
          :class="['min-h-28 p-2 border-b border-r border-gray-100 transition-colors cursor-pointer',
            !cell.inMonth && 'bg-gray-50/50 opacity-40',
            cell.isToday && 'bg-indigo-50/40',
            cell.inMonth && 'hover:bg-gray-50']"
        >
          <span :class="['inline-flex items-center justify-center w-7 h-7 rounded-full text-sm font-medium mb-1',
            cell.isToday ? 'bg-indigo-600 text-white' : 'text-gray-700']">
            {{ cell.day }}
          </span>
          <div class="space-y-0.5">
            <div v-for="e in cell.expenses" :key="e.id"
              @click.stop="openDetail(e)"
              :class="['flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium truncate', badgeClass(e.type)]">
              <span>{{ expenseIcon(e.type) }}</span>
              <span class="truncate">{{ label(e) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <ExpenseModal v-if="showModal" :date="selectedDate!" :expense="selectedExpense" @close="closeModal" @saved="onSaved" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { usePersonStore } from '@/stores/personStore'
import { useExpenseStore } from '@/stores/expenseStore'
import type { Expense, TravelExpense, TollExpense } from '@/types'
import ExpenseModal from '@/components/expense/ExpenseModal.vue'

const personStore = usePersonStore()
const expenseStore = useExpenseStore()

const today = new Date()
const year = ref(today.getFullYear())
const month = ref(today.getMonth())
const monthNames = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre']
const years = Array.from({ length: 8 }, (_, i) => today.getFullYear() - 5 + i)
const showModal = ref(false)
const selectedDate = ref<string | null>(null)
const selectedExpense = ref<Expense | null>(null)

function toDateStr(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const from = computed(() => toDateStr(new Date(year.value, month.value, 1)))
const to = computed(() => toDateStr(new Date(year.value, month.value + 1, 0)))

const cells = computed(() => {
  const first = new Date(year.value, month.value, 1)
  const last = new Date(year.value, month.value + 1, 0)
  const offset = (first.getDay() + 6) % 7
  const result = []
  const todayStr = toDateStr(today)

  for (let i = 0; i < offset; i++) {
    const d = new Date(year.value, month.value, 1 - (offset - i))
    const dateStr = toDateStr(d)
    result.push({ date: dateStr, day: d.getDate(), inMonth: false, isToday: false, expenses: [] as Expense[] })
  }
  for (let d = 1; d <= last.getDate(); d++) {
    const dateStr = toDateStr(new Date(year.value, month.value, d))
    result.push({ date: dateStr, day: d, inMonth: true, isToday: dateStr === todayStr, expenses: expenseStore.expenses.filter(e => e.date === dateStr) })
  }
  const total = Math.ceil(result.length / 7) * 7
  for (let n = 1; result.length < total; n++) {
    const d = new Date(year.value, month.value + 1, n)
    result.push({ date: toDateStr(d), day: d.getDate(), inMonth: false, isToday: false, expenses: [] as Expense[] })
  }
  return result
})

const stats = computed(() => {
  const es = expenseStore.expenses
  return {
    travelCount: es.filter(e => e.type === 'travel').length,
    travelKm: es.filter(e => e.type === 'travel').reduce((s, e) => s + ((e as TravelExpense).distanceKm ?? 0), 0),
    remoteCount: es.filter(e => e.type === 'remote_work').length,
    tollCount: es.filter(e => e.type === 'toll').length,
    tollAmount: es.filter(e => e.type === 'toll').reduce((s, e) => s + ((e as TollExpense).tollAmount ?? 0), 0),
  }
})

function badgeClass(type: string) {
  return ({ travel: 'bg-blue-100 text-blue-700', remote_work: 'bg-emerald-100 text-emerald-700', toll: 'bg-amber-100 text-amber-700' } as Record<string,string>)[type] ?? ''
}
function expenseIcon(type: string) {
  return ({ travel: '🚗', remote_work: '🏠', toll: '🛣️' } as Record<string,string>)[type] ?? '📌'
}
function label(e: Expense): string {
  if (e.type === 'travel') { const t = e as TravelExpense; return t.arrival ? `→ ${t.arrival}` : `${t.distanceKm} km` }
  if (e.type === 'remote_work') return '2,50 €'
  if (e.type === 'toll') return `${(e as TollExpense).tollAmount.toFixed(2)} €`
  return ''
}
function openModal(date: string) { selectedDate.value = date; selectedExpense.value = null; showModal.value = true }
function openDetail(e: Expense) { selectedDate.value = e.date; selectedExpense.value = e; showModal.value = true }
function closeModal() { showModal.value = false; selectedExpense.value = null }
async function onSaved() { closeModal(); await load() }
async function load() { await expenseStore.fetchByPeriod(from.value, to.value, personStore.activePerson?.id) }
function prevMonth() { if (month.value === 0) { month.value = 11; year.value-- } else month.value-- }
function nextMonth() { if (month.value === 11) { month.value = 0; year.value++ } else month.value++ }
function goToday() { month.value = today.getMonth(); year.value = today.getFullYear() }

watch([month, year, () => personStore.activePerson], load)
onMounted(load)
</script>
