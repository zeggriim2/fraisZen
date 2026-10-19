<template>
  <div class="workspace-page">
    <PageHeader eyebrow="Mon quotidien" title="Un mois bien organisé." description="Vos déplacements et vos frais, au même endroit.">
      <template #actions>
        <BaseButton v-if="personStore.activePerson" icon="plus" @click="openModal(focusedDate)">Déclarer un frais</BaseButton>
      </template>
    </PageHeader>

    <EmptyState v-if="!personStore.loading && !personStore.persons.length" icon="users" title="Votre espace commence ici" description="Créez un profil pour ajouter vos premiers frais professionnels.">
      <template #actions><BaseButton to="/persons" icon="plus">Créer mon profil</BaseButton></template>
    </EmptyState>

    <template v-else>
      <div class="month-overview" aria-label="Résumé du mois">
        <div><span class="overview-icon blue" aria-hidden="true"><AppIcon name="car" /></span><div><span>Déplacements</span><strong>{{ stats.travelKm.toFixed(0) }} <small>km</small></strong></div><span class="overview-note">{{ stats.travelCount }} trajet{{ stats.travelCount > 1 ? 's' : '' }}</span></div>
        <div><span class="overview-icon mint" aria-hidden="true"><AppIcon name="home" /></span><div><span>Télétravail</span><strong>{{ stats.remoteCount }} <small>jour{{ stats.remoteCount > 1 ? 's' : '' }}</small></strong></div><span class="overview-note">{{ stats.remoteTotalAmount.toFixed(2) }} €</span></div>
        <div><span class="overview-icon peach" aria-hidden="true"><AppIcon name="road" /></span><div><span>Péages</span><strong>{{ stats.tollAmount.toFixed(2) }} <small>€</small></strong></div><span class="overview-note">{{ stats.tollCount }} frais</span></div>
      </div>

      <div class="calendar-layout">
        <section class="calendar-panel" aria-labelledby="calendar-title">
          <h3 id="calendar-title" class="sr-only">Calendrier des frais</h3>
          <div class="calendar-toolbar">
            <div class="period-control">
              <button type="button" class="icon-button" :aria-label="viewMode === 'week' ? 'Semaine précédente' : 'Mois précédent'" @click="prevMonth"><AppIcon name="chevron-left" /></button>
              <label for="calendar-month" class="sr-only">Mois</label>
              <select id="calendar-month" v-model="month"><option v-for="(name, i) in monthNames" :key="i" :value="i">{{ name }}</option></select>
              <label for="calendar-year" class="sr-only">Année</label>
              <select id="calendar-year" v-model="year"><option v-for="y in years" :key="y" :value="y">{{ y }}</option></select>
              <button type="button" class="icon-button" :aria-label="viewMode === 'week' ? 'Semaine suivante' : 'Mois suivant'" @click="nextMonth"><AppIcon name="chevron-right" /></button>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
              <BaseButton variant="secondary" size="sm" @click="goToday">Aujourd’hui</BaseButton>
              <div class="segmented-control" role="group" aria-label="Affichage du calendrier">
                <button type="button" :aria-pressed="viewMode === 'month'" :class="{active: viewMode === 'month'}" @click="viewMode = 'month'">Mois</button>
                <button type="button" :aria-pressed="viewMode === 'week'" :class="{active: viewMode === 'week'}" @click="viewMode = 'week'">Semaine</button>
              </div>
            </div>
          </div>

          <div class="category-filters" role="group" aria-label="Filtrer par type de frais">
            <button type="button" :class="{active:selectedCategory === 'all'}" @click="selectedCategory = 'all'" :aria-pressed="selectedCategory === 'all'">Tous les frais</button>
            <button v-for="t in EXPENSE_TYPES" :key="t.value" type="button" :class="{active:selectedCategory === t.value}" :aria-pressed="selectedCategory === t.value" @click="selectedCategory = t.value"><span :class="['category-dot',t.dotClass]" aria-hidden="true" />{{ t.label }}</button>
          </div>

          <AlertBanner v-if="expenseStore.error" variant="danger" class="mx-5 my-2">
            {{ expenseStore.error }}
            <template #actions><BaseButton variant="secondary" size="sm" icon="refresh" @click="load">Réessayer</BaseButton></template>
          </AlertBanner>
          <p v-if="expenseStore.loading" role="status" class="flex items-center gap-2 px-5 py-2 text-xs text-ink-subtle"><span class="spinner" style="width:0.875rem;height:0.875rem" aria-hidden="true" />Chargement des frais…</p>

          <div class="calendar-weekdays" aria-hidden="true"><span v-for="d in dayHeaders" :key="d.abbr"><span class="sm:hidden">{{ d.letter }}</span><span class="hidden sm:inline">{{ d.abbr }}</span></span></div>
          <div class="calendar-grid" :class="{'week-view':viewMode === 'week'}" role="grid" aria-label="Jours du calendrier">
            <div
              v-for="(cell,i) in visibleCells"
              :key="cell.date"
              class="calendar-day"
              :class="{outside:!cell.inMonth, weekend:i % 7 > 4, selected:cell.date === focusedDate, today:cell.isToday}"
              role="gridcell"
              :aria-label="`${cellLabel(cell.date)} : ${cell.expenses.length} frais${cell.isToday ? ', aujourd’hui' : ''}`"
              :aria-selected="cell.date === focusedDate"
              tabindex="0"
              @click="focusDay(cell.date)"
              @keydown.enter.prevent="focusDay(cell.date)"
              @keydown.space.prevent="focusDay(cell.date)"
            >
              <div class="day-selector">
                <span>{{ cell.day }}</span>
                <button
                  type="button"
                  class="day-plus"
                  :aria-label="`Ajouter un frais le ${cellLabel(cell.date)}`"
                  title="Ajouter un frais"
                  @click.stop="openExpenseForDay(cell.date)"
                ><AppIcon name="plus" :size="14" /></button>
              </div>
              <span v-if="cell.isHoliday" class="holiday-label" :title="cell.holidayName ?? ''">{{ cell.holidayName }}</span>
              <button v-for="e in cell.expenses.slice(0,viewMode === 'week' ? 10 : 2)" :key="e.id" type="button" class="calendar-event" :class="badgeClass(e.type)" @click.stop="openDetail(e)" :title="label(e)"><span :class="['category-dot',dotClass(e.type)]" aria-hidden="true" /><span class="event-label">{{ label(e) }}</span></button>
              <button v-if="cell.expenses.length > (viewMode === 'week' ? 10 : 2)" type="button" class="more-events" @click.stop="focusDay(cell.date)">+{{ cell.expenses.length - (viewMode === 'week' ? 10 : 2) }} <span class="hidden sm:inline">frais</span></button>
            </div>
          </div>
          <div class="calendar-bottom"><span>Sélectionnez un jour pour retrouver ses frais</span><RouterLink :to="{ path: '/summary', query: { year } }" class="btn-link text-xs">Récapitulatif annuel<AppIcon name="arrow-right" :size="14" /></RouterLink></div>
        </section>

        <aside class="day-panel" aria-labelledby="day-panel-title">
          <div class="flex justify-between items-center"><span class="eyebrow">Votre journée</span><span class="badge badge-neutral">{{ dayExpenses.length }} frais</span></div>
          <h3 id="day-panel-title">{{ focusedLabel }}</h3>
          <EmptyState v-if="!dayExpenses.length" icon="calendar" title="Une journée à compléter" compact class="!border-0 !bg-surface-muted !py-6">
            Aucun frais{{ selectedCategory !== 'all' ? ' de ce type' : '' }} enregistré.
          </EmptyState>
          <div v-else class="day-expenses">
            <button v-for="e in dayExpenses" :key="e.id" type="button" @click="openDetail(e)">
              <span :class="['expense-mini-icon',badgeClass(e.type)]" aria-hidden="true"><AppIcon :name="e.type === 'travel' ? 'car' : e.type === 'remote_work' ? 'home' : 'receipt'" /></span>
              <span><strong>{{ EXPENSE_TYPES.find(t => t.value === e.type)?.label }}</strong><small>{{ label(e) }}</small></span>
              <AppIcon name="chevron-right" :size="18" class="ml-auto text-ink-subtle" />
            </button>
          </div>
          <BaseButton variant="secondary" block icon="plus" @click="openModal(focusedDate)">Ajouter à cette journée</BaseButton>
          <div class="routine-panel">
            <span class="eyebrow">Gagnez du temps</span>
            <AppIcon name="repeat" :size="28" />
            <h4>Un trajet.<br />Toute votre semaine.</h4>
            <p>Préparez vos déplacements habituels en une seule fois.</p>
            <BaseButton trailing-icon="arrow-right" @click="showBulkModal = true">Trajets récurrents</BaseButton>
          </div>
          <button type="button" class="import-action" @click="showCsvImport = true"><AppIcon name="upload" /><span>Importer un relevé CSV</span><AppIcon name="chevron-right" :size="18" class="ml-auto text-ink-subtle" /></button>
        </aside>
      </div>
    </template>

    <BulkTripModal v-if="showBulkModal && personStore.activePerson" :person-id="personStore.activePerson.id" :year="year" :month="month" :public-holidays="publicHolidays" @close="showBulkModal = false" @generated="onBulkGenerated" />
    <ExpenseModal v-if="showModal" :date="selectedDate!" :expense="selectedExpense" :prefill="duplicateSource" @close="closeModal" @saved="onSaved" @duplicate="onDuplicate" />
    <CsvImportModal v-if="showCsvImport" @close="showCsvImport = false" @imported="onCsvImported" />
  </div>
</template>
<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { usePersonStore } from '@/stores/personStore'
import { useExpenseStore } from '@/stores/expenseStore'
import { useAuthStore } from '@/stores/authStore'
import { useToast } from '@/composables/useToast'
import { EXPENSE_TYPES, expenseBadgeClass, expenseDotClass } from '@/utils/expense'
import type { Expense, TravelExpense, TollExpense, MealExpense } from '@/types'
import ExpenseModal from '@/components/expense/ExpenseModal.vue'
import CsvImportModal from '@/components/expense/CsvImportModal.vue'
import BulkTripModal from '@/components/expense/BulkTripModal.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import AlertBanner from '@/components/ui/AlertBanner.vue'
import { ParkingExpense } from '@/types'
import { getPublicHolidays } from '@/api/expenseApi'

const { show: showToast } = useToast()


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
const showBulkModal = ref(false)
const selectedDate = ref<string | null>(null)
const selectedExpense = ref<Expense | null>(null)
const duplicateSource = ref<Expense | null>(null)

function toDateStr(d: Date): string {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const from = computed(() => toDateStr(new Date(year.value, month.value, 1)))
const to = computed(() => toDateStr(new Date(year.value, month.value + 1, 0)))

const viewMode = ref<'month' | 'week'>('month')
const focusedDate = ref(toDateStr(new Date(year.value, month.value, Math.min(today.getDate(), new Date(year.value, month.value + 1, 0).getDate()))))
const selectedCategory = ref('all')
const filteredExpenses = computed(() => expenseStore.expenses.filter(e => selectedCategory.value === 'all' || e.type === selectedCategory.value))
const dayExpenses = computed(() => filteredExpenses.value.filter(e => e.date === focusedDate.value))
function cellLabel(date: string): string { return new Date(date + 'T12:00:00').toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }) }
const focusedLabel = computed(() => new Date(focusedDate.value + 'T12:00:00').toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }))
const visibleCells = computed(() => {
  if (viewMode.value === 'month') return cells.value
  const index = cells.value.findIndex(c => c.date === focusedDate.value)
  const start = index < 0 ? 0 : Math.floor(index / 7) * 7
  return cells.value.slice(start, start + 7)
})
function focusDay(date: string) {
  focusedDate.value = date
  const selected = new Date(date + 'T12:00:00')
  month.value = selected.getMonth()
  year.value = selected.getFullYear()
}
function moveWeek(direction: number) {
  const selected = new Date(focusedDate.value + 'T12:00:00')
  selected.setDate(selected.getDate() + direction * 7)
  focusDay(toDateStr(selected))
}
const cells = computed(() => {
  const first = new Date(year.value, month.value, 1)
  const last = new Date(year.value, month.value + 1, 0)
  const offset = (first.getDay() + 6) % 7
  const result = []
  const todayStr = toDateStr(today)

  for (let i = 0; i < offset; i++) {
    const d = new Date(year.value, month.value, 1 - (offset - i))
    result.push({ date: toDateStr(d), day: d.getDate(), inMonth: false, isToday: false, isHoliday: false, holidayName: null, expenses: filteredExpenses.value.filter(e => e.date === toDateStr(d)) })
  }
  for (let d = 1; d <= last.getDate(); d++) {
    const dateStr = toDateStr(new Date(year.value, month.value, d))
    result.push({ date: dateStr, day: d, inMonth: true, isToday: dateStr === todayStr, isHoliday: !!publicHolidays.value[dateStr], holidayName: publicHolidays.value[dateStr] ?? null, expenses: filteredExpenses.value.filter(e => e.date === dateStr) })
  }
  const total = Math.ceil(result.length / 7) * 7
  for (let n = 1; result.length < total; n++) {
    const d = new Date(year.value, month.value + 1, n)
    result.push({ date: toDateStr(d), day: d.getDate(), inMonth: false, isToday: false, isHoliday: false, holidayName: null, expenses: filteredExpenses.value.filter(e => e.date === toDateStr(d)) })
  }
  return result
})

const stats = computed(() => {
  const es = expenseStore.expenses.filter(e => e.date >= from.value && e.date <= to.value)
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

function badgeClass(type: string) { return expenseBadgeClass(type) }
function dotClass(type: string) { return expenseDotClass(type) }

function label(e: Expense): string {
  if (e.type === 'travel') { const t = e as TravelExpense; return t.arrival ? `→ ${t.arrival}` : `${t.distanceKm} km` }
  if (e.type === 'remote_work') return `${e.amount.toFixed(2)} €`
  if (e.type === 'toll') return `${(e as TollExpense).tollAmount.toFixed(2)} €`
  if (e.type === 'meal') return `${(e as MealExpense).mealAmount.toFixed(2)} €`
  if (e.type === 'parking') return `${(e as ParkingExpense).parkingAmount.toFixed(2)} €`
  return ''
}

function openModal(date: string) { selectedDate.value = date; selectedExpense.value = null; showModal.value = true }
function openExpenseForDay(date: string) {
  focusDay(date)
  openModal(date)
}
function openDetail(e: Expense) { selectedDate.value = e.date; selectedExpense.value = e; showModal.value = true }
function closeModal() { showModal.value = false; selectedExpense.value = null; duplicateSource.value = null }
async function onSaved() { closeModal(); await load(); showToast('Dépense enregistrée') }
function onDuplicate(e: Expense) {
  showModal.value = false
  selectedDate.value = toDateStr(new Date())
  selectedExpense.value = null
  duplicateSource.value = e
  showModal.value = true
}
async function load() {
  const start = new Date(year.value, month.value, 1)
  start.setDate(start.getDate() - (start.getDay() + 6) % 7)
  const end = new Date(year.value, month.value + 1, 0)
  end.setDate(end.getDate() + (7 - end.getDay()) % 7)
  await expenseStore.fetchByPeriod(toDateStr(start), toDateStr(end), personStore.activePerson?.id)
}
async function onCsvImported(count: number) { showCsvImport.value = false; await load(); if (count) showToast(`${count} frais importés avec succès`) }
async function onBulkGenerated(count: number) { showBulkModal.value = false; await load(); if (count) showToast(`${count} trajet${count > 1 ? 's' : ''} généré${count > 1 ? 's' : ''} avec succès`) }
function prevMonth() { if (viewMode.value === 'week') { moveWeek(-1); return } if (month.value === 0) { month.value = 11; year.value-- } else month.value-- }
function nextMonth() { if (viewMode.value === 'week') { moveWeek(1); return } if (month.value === 11) { month.value = 0; year.value++ } else month.value++ }
function goToday() { focusedDate.value = toDateStr(today); month.value = today.getMonth(); year.value = today.getFullYear() }

watch([month, year], () => { if (!focusedDate.value.startsWith(`${year.value}-${String(month.value + 1).padStart(2, '0')}`)) focusedDate.value = toDateStr(new Date(year.value, month.value, 1)) })
watch(year, loadHolidays, { immediate: true })
watch([month, year, () => personStore.activePerson], load)
onMounted(load)
</script>
