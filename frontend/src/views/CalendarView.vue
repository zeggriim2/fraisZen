<template>
  <div class="p-4 sm:p-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
      <!-- Navigation mois / année -->
      <div class="flex items-center gap-1.5 min-w-0">
        <button @click="prevMonth" class="p-2 rounded-lg hover:bg-gray-100 shrink-0">
          <svg class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <select v-model="month" class="text-base font-semibold text-gray-900 border-0 bg-transparent cursor-pointer focus:ring-0 capitalize min-w-0">
          <option v-for="(name, i) in monthNames" :key="i" :value="i">{{ name }}</option>
        </select>
        <select v-model="year" class="text-base font-semibold text-gray-900 border-0 bg-transparent cursor-pointer focus:ring-0 w-20">
          <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
        </select>
        <button @click="nextMonth" class="p-2 rounded-lg hover:bg-gray-100 shrink-0">
          <svg class="w-5 h-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>
        <button @click="goToday" class="px-3 py-1.5 text-sm font-medium text-indigo-600 border border-indigo-200 rounded-lg hover:bg-indigo-50 shrink-0">
          Aujourd'hui
        </button>
      </div>
      <!-- Personne + import CSV -->
      <div class="flex items-center gap-3 sm:ml-auto">
        <span v-if="personStore.activePerson" class="text-sm text-gray-500 truncate">
          <span class="font-medium text-gray-900">{{ personStore.activePerson.fullName }}</span>
        </span>
        <button @click="showCsvImport = true"
          class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 shrink-0"
          title="Importer depuis un relevé bancaire CSV">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
          <span class="hidden sm:inline">Import CSV</span>
          <span class="sm:hidden">CSV</span>
        </button>
      </div>
    </div>

    <!-- État vide : aucune personne existante -->
    <div v-if="!personStore.loading && personStore.persons.length === 0" class="flex flex-col items-center justify-center py-24 text-center">
      <div class="w-24 h-24 rounded-full bg-indigo-50 flex items-center justify-center mb-6">
        <svg class="w-12 h-12 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
        </svg>
      </div>
      <h3 class="text-lg font-semibold text-gray-900 mb-2">Bienvenue sur Frais Réels !</h3>
      <p class="text-sm text-gray-500 mb-6 max-w-sm">Commencez par créer un profil pour la personne dont vous voulez déclarer les frais professionnels.</p>
      <RouterLink to="/persons" class="flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-medium hover:bg-indigo-700 transition-colors shadow-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Créer mon premier profil
      </RouterLink>
    </div>

    <template v-else>

      <!-- Stats -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
          <p class="text-xs text-blue-600 font-semibold uppercase tracking-wide">Trajets</p>
          <p class="text-2xl font-bold text-blue-700 mt-1">{{ stats.travelCount }}</p>
          <p class="text-xs text-blue-500 mt-1">{{ stats.travelKm.toFixed(0) }} km</p>
        </div>
        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4">
          <p class="text-xs text-emerald-600 font-semibold uppercase tracking-wide">Télétravail</p>
          <p class="text-2xl font-bold text-emerald-700 mt-1">{{ stats.remoteCount }}</p>
          <p class="text-xs text-emerald-500 mt-1">{{ stats.remoteTotalAmount.toFixed(2) }} €</p>
        </div>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
          <p class="text-xs text-amber-600 font-semibold uppercase tracking-wide">Péages</p>
          <p class="text-2xl font-bold text-amber-700 mt-1">{{ stats.tollCount }}</p>
          <p class="text-xs text-amber-500 mt-1">{{ stats.tollAmount.toFixed(2) }} €</p>
        </div>
      </div>

      <!-- Calendrier -->
      <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm">
        <!-- En-têtes des jours -->
        <div class="grid grid-cols-7 border-b border-gray-200">
          <div v-for="d in dayHeaders" :key="d.abbr"
            class="py-2 sm:py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">
            <span class="sm:hidden">{{ d.letter }}</span>
            <span class="hidden sm:inline">{{ d.abbr }}</span>
          </div>
        </div>

        <!-- Cellules -->
        <div class="grid grid-cols-7">
          <div
            v-for="(cell, i) in cells" :key="i"
            @click="cell.date && openModal(cell.date)"
            :class="[
              'min-h-10 sm:min-h-28 p-1 sm:p-2 border-b border-r border-gray-100 transition-colors cursor-pointer',
              !cell.inMonth && 'bg-gray-50/50 opacity-40',
              cell.isHoliday && cell.inMonth && 'bg-amber-50',
              cell.isToday && 'bg-indigo-50/40',
              cell.inMonth && !cell.isHoliday && 'hover:bg-gray-50',
              cell.inMonth && cell.isHoliday && 'hover:bg-amber-100',
            ]"
          >
            <!-- Numéro du jour + nom du jour férié (desktop) -->
            <div class="flex items-start justify-between gap-1 mb-0.5 sm:mb-1">
              <span :class="[
                'inline-flex items-center justify-center rounded-full font-medium shrink-0',
                'w-6 h-6 text-xs sm:w-7 sm:h-7 sm:text-sm',
                cell.isToday ? 'bg-indigo-600 text-white' : 'text-gray-700',
              ]">{{ cell.day }}</span>
              <span v-if="cell.isHoliday && cell.inMonth"
                class="hidden sm:block text-xs text-amber-600 font-medium truncate leading-tight mt-0.5"
                :title="cell.holidayName ?? ''">
                {{ cell.holidayName }}
              </span>
            </div>

            <!-- Mobile : points colorés -->
            <div v-if="cell.expenses.length" class="sm:hidden flex flex-wrap gap-0.5 mt-0.5">
              <span
                v-for="e in cell.expenses" :key="e.id"
                @click.stop="openDetail(e)"
                :class="['w-1.5 h-1.5 rounded-full shrink-0', dotClass(e.type)]"
              />
            </div>

            <!-- Desktop : badges avec texte -->
            <div class="hidden sm:block space-y-0.5">
              <div
                v-for="e in cell.expenses" :key="e.id"
                @click.stop="openDetail(e)"
                :class="['flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium truncate', badgeClass(e.type)]">
                <span>{{ expenseIcon(e) }}</span>
                <span class="truncate">{{ label(e) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <ExpenseModal v-if="showModal" :date="selectedDate!" :expense="selectedExpense" :prefill="duplicateSource" @close="closeModal" @saved="onSaved" @duplicate="onDuplicate" />
      <CsvImportModal v-if="showCsvImport" @close="showCsvImport = false" @imported="onCsvImported" />

    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { usePersonStore } from '@/stores/personStore'
import { useExpenseStore } from '@/stores/expenseStore'
import { useAuthStore } from '@/stores/authStore'
import type { Expense, TravelExpense, TollExpense, MealExpense } from '@/types'
import ExpenseModal from '@/components/expense/ExpenseModal.vue'
import CsvImportModal from '@/components/expense/CsvImportModal.vue'
import { ParkingExpense } from '@/types'
import { getPublicHolidays } from '@/api/expenseApi'

const personStore = usePersonStore()
const expenseStore = useExpenseStore()
const authStore = useAuthStore()

const today = new Date()
const year = ref(authStore.user?.defaultYear ?? today.getFullYear())
const month = ref(today.getMonth())
const publicHolidays = ref<Record<string, string>>({})

const dayHeaders = [
  { letter: 'L', abbr: 'Lun' },
  { letter: 'M', abbr: 'Mar' },
  { letter: 'M', abbr: 'Mer' },
  { letter: 'J', abbr: 'Jeu' },
  { letter: 'V', abbr: 'Ven' },
  { letter: 'S', abbr: 'Sam' },
  { letter: 'D', abbr: 'Dim' },
]

async function loadHolidays() {
  try {
    publicHolidays.value = await getPublicHolidays(year.value)
  } catch {
    publicHolidays.value = {}
  }
}

watch(() => authStore.user, (u) => {
  if (u?.defaultYear != null) year.value = u.defaultYear
}, { immediate: true })

const monthNames = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre']
const years = Array.from({ length: 8 }, (_, i) => today.getFullYear() - 5 + i)
const showModal = ref(false)
const showCsvImport = ref(false)
const selectedDate = ref<string | null>(null)
const selectedExpense = ref<Expense | null>(null)
const duplicateSource = ref<Expense | null>(null)

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
    result.push({ date: toDateStr(d), day: d.getDate(), inMonth: false, isToday: false, isHoliday: false, holidayName: null, expenses: [] as Expense[] })
  }
  for (let d = 1; d <= last.getDate(); d++) {
    const dateStr = toDateStr(new Date(year.value, month.value, d))
    result.push({ date: dateStr, day: d, inMonth: true, isToday: dateStr === todayStr, isHoliday: !!publicHolidays.value[dateStr], holidayName: publicHolidays.value[dateStr] ?? null, expenses: expenseStore.expenses.filter(e => e.date === dateStr) })
  }
  const total = Math.ceil(result.length / 7) * 7
  for (let n = 1; result.length < total; n++) {
    const d = new Date(year.value, month.value + 1, n)
    result.push({ date: toDateStr(d), day: d.getDate(), inMonth: false, isToday: false, isHoliday: false, holidayName: null, expenses: [] as Expense[] })
  }
  return result
})

const stats = computed(() => {
  const es = expenseStore.expenses
  const remote = es.filter(e => e.type === 'remote_work')
  return {
    travelCount: es.filter(e => e.type === 'travel').length,
    travelKm: es.filter(e => e.type === 'travel').reduce((s, e) => { const t = e as TravelExpense; return s + ((t.distanceKm ?? 0) * (t.roundTrip ? 2 : 1)) }, 0),
    remoteCount: remote.length,
    remoteTotalAmount: remote.reduce((s, e) => s + e.amount, 0),
    tollCount: es.filter(e => e.type === 'toll').length,
    tollAmount: es.filter(e => e.type === 'toll').reduce((s, e) => s + ((e as TollExpense).tollAmount ?? 0), 0),
  }
})

function badgeClass(type: string) {
  return ({
    travel:      'bg-blue-100 text-blue-700',
    remote_work: 'bg-emerald-100 text-emerald-700',
    toll:        'bg-amber-100 text-amber-700',
    meal:        'bg-orange-100 text-orange-700',
    parking:     'bg-red-100 text-red-700',
  } as Record<string, string>)[type] ?? ''
}

function dotClass(type: string) {
  return ({
    travel:      'bg-blue-500',
    remote_work: 'bg-emerald-500',
    toll:        'bg-amber-500',
    meal:        'bg-orange-500',
    parking:     'bg-rose-500',
  } as Record<string, string>)[type] ?? 'bg-gray-400'
}

function expenseIcon(e: Expense) {
  if (e.type === 'travel' && (e as TravelExpense).isElectric) return '⚡'
  return ({ travel: '🚗', remote_work: '🏠', toll: '🛣️', meal: '🍽️', parking: '🅿️' } as Record<string, string>)[e.type] ?? '📌'
}

function label(e: Expense): string {
  if (e.type === 'travel') { const t = e as TravelExpense; return t.arrival ? `→ ${t.arrival}` : `${t.distanceKm} km` }
  if (e.type === 'remote_work') return `${e.amount.toFixed(2)} €`
  if (e.type === 'toll') return `${(e as TollExpense).tollAmount.toFixed(2)} €`
  if (e.type === 'meal') return `${(e as MealExpense).mealAmount.toFixed(2)} €`
  if (e.type === 'parking') return `${(e as ParkingExpense).parkingAmount.toFixed(2)} €`
  return ''
}

function openModal(date: string) { selectedDate.value = date; selectedExpense.value = null; showModal.value = true }
function openDetail(e: Expense) { selectedDate.value = e.date; selectedExpense.value = e; showModal.value = true }
function closeModal() { showModal.value = false; selectedExpense.value = null; duplicateSource.value = null }
async function onSaved() { closeModal(); await load() }
function onDuplicate(e: Expense) {
  showModal.value = false
  selectedDate.value = toDateStr(new Date())
  selectedExpense.value = null
  duplicateSource.value = e
  showModal.value = true
}
async function load() { await expenseStore.fetchByPeriod(from.value, to.value, personStore.activePerson?.id) }
async function onCsvImported(count: number) { showCsvImport.value = false; await load(); if (count) alert(`${count} frais importés avec succès.`) }
function prevMonth() { if (month.value === 0) { month.value = 11; year.value-- } else month.value-- }
function nextMonth() { if (month.value === 11) { month.value = 0; year.value++ } else month.value++ }
function goToday() { month.value = today.getMonth(); year.value = today.getFullYear() }

watch(year, loadHolidays, { immediate: true })
watch([month, year, () => personStore.activePerson], load)
onMounted(load)
</script>
