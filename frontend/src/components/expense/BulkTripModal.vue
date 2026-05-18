<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="$emit('close')" />

    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] flex flex-col">
      <!-- Header -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <div>
          <h2 class="text-base font-semibold text-gray-900">Générer des trajets</h2>
          <p class="text-xs text-gray-400 mt-0.5">Créez plusieurs trajets d'un coup sur une plage de dates</p>
        </div>
        <button @click="$emit('close')" class="text-gray-400 hover:text-gray-600 transition-colors">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <div class="overflow-y-auto flex-1 px-6 py-5 space-y-5">

        <!-- Étape 1 : plage de dates -->
        <section>
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">1. Période</h3>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-gray-500 mb-1">Du</label>
              <input v-model="form.from" type="date" :max="form.to"
                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Au</label>
              <input v-model="form.to" type="date" :min="form.from"
                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300" />
            </div>
          </div>
        </section>

        <!-- Étape 2 : jours de la semaine -->
        <section>
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">2. Jours travaillés</h3>
          <div class="flex gap-2 flex-wrap">
            <button v-for="d in weekDays" :key="d.value"
              type="button"
              @click="toggleDay(d.value)"
              :class="[
                'px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors',
                form.weekDays.includes(d.value)
                  ? 'bg-indigo-600 text-white border-indigo-600'
                  : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-300'
              ]">
              {{ d.label }}
            </button>
          </div>
          <p class="text-xs text-gray-400 mt-2">Les jours fériés sont automatiquement exclus.</p>
        </section>

        <!-- Étape 3 : trajet -->
        <section>
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">3. Trajet</h3>

          <!-- Sélecteur de trajet favori -->
          <div v-if="loadingRoutes" class="flex items-center gap-2 text-sm text-gray-400 py-2">
            <div class="w-4 h-4 border-2 border-indigo-200 border-t-indigo-500 rounded-full animate-spin"></div>
            Chargement des trajets favoris…
          </div>

          <div v-else-if="favoriteRoutes.length" class="mb-3">
            <label class="block text-xs text-gray-500 mb-1">Trajet favori</label>
            <select v-model="selectedRouteId"
              @change="applyFavoriteRoute"
              class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
              <option value="">— Saisie manuelle —</option>
              <option v-for="r in favoriteRoutes" :key="r.id" :value="r.id">
                {{ r.name }} ({{ r.roundTrip ? 'A/R' : 'simple' }} · {{ r.vehicleType === 'car' ? '🚗' : r.vehicleType === 'motorcycle' ? '🏍️' : '🛵' }})
              </option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-gray-500 mb-1">Départ</label>
              <input v-model="form.departure" type="text" placeholder="ex : Paris 13e"
                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Arrivée</label>
              <input v-model="form.arrival" type="text" placeholder="ex : La Défense"
                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3 mt-3">
            <div>
              <label class="block text-xs text-gray-500 mb-1">Distance (km)</label>
              <input v-model.number="form.distanceKm" type="number" min="0.1" step="0.1" placeholder="ex : 18"
                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Véhicule</label>
              <select v-model="form.vehicleType"
                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                <option value="car">Voiture</option>
                <option value="motorcycle">Moto</option>
                <option value="moped">Scooter</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3 mt-3">
            <div>
              <label class="block text-xs text-gray-500 mb-1">CV fiscal</label>
              <select v-model="form.vehiclePower"
                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
                <option :value="null">—</option>
                <option v-for="p in [3,4,5,6,7]" :key="p" :value="p">{{ p }} CV</option>
              </select>
            </div>
            <div class="flex items-end pb-0.5">
              <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                <input v-model="form.roundTrip" type="checkbox"
                  class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-300" />
                Aller-retour
              </label>
            </div>
          </div>

          <div v-if="form.vehicleType === 'car'" class="flex items-center justify-between p-3 rounded-xl border border-gray-200 bg-gray-50 mt-3">
            <div>
              <p class="text-sm font-medium text-gray-700">Véhicule électrique</p>
              <p class="text-xs text-gray-500 mt-0.5">Majoration de +20 % sur l'indemnité calculée</p>
            </div>
            <button type="button" @click="form.isElectric = !form.isElectric"
              :class="['relative inline-flex h-6 w-11 items-center rounded-full transition-colors', form.isElectric ? 'bg-emerald-600' : 'bg-gray-300']">
              <span :class="['inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform', form.isElectric ? 'translate-x-6' : 'translate-x-1']" />
            </button>
          </div>
        </section>

        <!-- Aperçu des dates -->
        <section v-if="previewDates.length || form.from">
          <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
            Aperçu
            <span class="ml-2 font-bold text-indigo-600">{{ previewDates.length }} trajets</span>
          </h3>

          <div v-if="!previewDates.length" class="text-xs text-gray-400 italic py-2">
            Aucune date à générer — vérifiez la période et les jours sélectionnés.
          </div>

          <div v-else class="max-h-36 overflow-y-auto border border-gray-100 rounded-xl divide-y divide-gray-50">
            <div v-for="d in previewDates" :key="d"
              class="flex items-center justify-between px-3 py-1.5 text-xs text-gray-700">
              <span>{{ formatDate(d) }}</span>
              <span v-if="publicHolidays[d]" class="text-amber-500 font-medium">{{ publicHolidays[d] }}</span>
            </div>
          </div>

          <p v-if="excludedHolidays > 0" class="text-xs text-amber-600 mt-2">
            {{ excludedHolidays }} jour{{ excludedHolidays > 1 ? 's' : '' }} férié{{ excludedHolidays > 1 ? 's' : '' }} exclu{{ excludedHolidays > 1 ? 's' : '' }}
          </p>
        </section>

        <p v-if="error" class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2">{{ error }}</p>
      </div>

      <!-- Footer -->
      <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between gap-3">
        <button @click="$emit('close')"
          class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
          Annuler
        </button>
        <button @click="generate"
          :disabled="!canGenerate || generating"
          class="flex items-center gap-2 px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors">
          <div v-if="generating" class="w-4 h-4 border-2 border-white/40 border-t-white rounded-full animate-spin"></div>
          {{ generating ? 'Génération…' : `Générer ${previewDates.length} trajet${previewDates.length > 1 ? 's' : ''}` }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { expenseApi, getPublicHolidays } from '@/api/expenseApi'
import { personApi } from '@/api/personApi'
import type { FavoriteRoute } from '@/types'

const props = defineProps<{
  personId: string
  year: number
  month: number
  publicHolidays: Record<string, string>
}>()

const emit = defineEmits<{
  close: []
  generated: [count: number]
}>()

const weekDays = [
  { value: 1, label: 'Lun' },
  { value: 2, label: 'Mar' },
  { value: 3, label: 'Mer' },
  { value: 4, label: 'Jeu' },
  { value: 5, label: 'Ven' },
  { value: 6, label: 'Sam' },
  { value: 0, label: 'Dim' },
]

function monthPad(n: number) { return String(n).padStart(2, '0') }
function defaultFrom() { return `${props.year}-${monthPad(props.month + 1)}-01` }
function defaultTo() {
  const last = new Date(props.year, props.month + 1, 0)
  return `${props.year}-${monthPad(props.month + 1)}-${monthPad(last.getDate())}`
}

const form = ref({
  from: defaultFrom(),
  to: defaultTo(),
  weekDays: [1, 2, 3, 4, 5] as number[],
  departure: '',
  arrival: '',
  distanceKm: null as number | null,
  vehicleType: 'car',
  vehiclePower: null as number | null,
  roundTrip: false,
  isElectric: false,
})

const selectedRouteId = ref('')
const favoriteRoutes = ref<FavoriteRoute[]>([])
const loadingRoutes = ref(false)
const generating = ref(false)
const error = ref('')

// Public holidays for years spanning the range (may cross year boundary)
const allHolidays = ref<Record<string, string>>({ ...props.publicHolidays })

async function loadHolidaysForRange() {
  const years = new Set<number>()
  if (form.value.from) years.add(parseInt(form.value.from.slice(0, 4)))
  if (form.value.to) years.add(parseInt(form.value.to.slice(0, 4)))
  for (const y of years) {
    if (y !== props.year) {
      try {
        const h = await getPublicHolidays(y)
        Object.assign(allHolidays.value, h)
      } catch { /* ignore */ }
    }
  }
}

watch([() => form.value.from, () => form.value.to], loadHolidaysForRange)

const excludedHolidays = ref(0)

const previewDates = computed(() => {
  if (!form.value.from || !form.value.to || !form.value.weekDays.length) return []

  const result: string[] = []
  let holidays = 0
  const cursor = new Date(form.value.from + 'T00:00:00')
  const end = new Date(form.value.to + 'T00:00:00')

  while (cursor <= end) {
    const dow = cursor.getDay()
    // Use local date parts to avoid UTC offset shifting the date (e.g. UTC+2 midnight → previous UTC day)
    const iso = `${cursor.getFullYear()}-${String(cursor.getMonth() + 1).padStart(2, '0')}-${String(cursor.getDate()).padStart(2, '0')}`

    if (form.value.weekDays.includes(dow)) {
      if (allHolidays.value[iso]) {
        holidays++
      } else {
        result.push(iso)
      }
    }
    cursor.setDate(cursor.getDate() + 1)
  }

  excludedHolidays.value = holidays
  return result
})

const canGenerate = computed(() =>
  previewDates.value.length > 0 &&
  (form.value.distanceKm ?? 0) > 0
)

function toggleDay(d: number) {
  const idx = form.value.weekDays.indexOf(d)
  if (idx >= 0) form.value.weekDays.splice(idx, 1)
  else form.value.weekDays.push(d)
}

function applyFavoriteRoute() {
  const route = favoriteRoutes.value.find(r => r.id === selectedRouteId.value)
  if (!route) return
  form.value.departure = route.departure
  form.value.arrival = route.arrival
  form.value.vehicleType = route.vehicleType
  form.value.vehiclePower = route.vehiclePower
  form.value.roundTrip = route.roundTrip
  form.value.isElectric = route.isElectric
}

const JOURS_FR = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.']
const MOIS_FR = ['jan', 'fév', 'mar', 'avr', 'mai', 'juin', 'juil', 'août', 'sep', 'oct', 'nov', 'déc']
function formatDate(iso: string) {
  const d = new Date(iso + 'T00:00:00')
  return `${JOURS_FR[d.getDay()]} ${d.getDate()} ${MOIS_FR[d.getMonth()]} ${d.getFullYear()}`
}

async function generate() {
  if (!canGenerate.value) return
  generating.value = true
  error.value = ''
  try {
    const { created } = await expenseApi.bulkCreateTravel({
      personId: props.personId,
      dates: previewDates.value,
      distanceKm: form.value.distanceKm!,
      vehiclePower: form.value.vehiclePower,
      departure: form.value.departure || null,
      arrival: form.value.arrival || null,
      roundTrip: form.value.roundTrip,
      vehicleType: form.value.vehicleType,
      isElectric: form.value.isElectric,
    })
    emit('generated', created)
  } catch {
    error.value = 'Une erreur est survenue. Vérifiez les données et réessayez.'
  } finally {
    generating.value = false
  }
}

onMounted(async () => {
  loadingRoutes.value = true
  try {
    favoriteRoutes.value = await personApi.getFavoriteRoutes(props.personId)
    if (favoriteRoutes.value.length) {
      selectedRouteId.value = favoriteRoutes.value[0].id
      applyFavoriteRoute()
    }
  } catch { /* trajets favoris non bloquants */ }
  finally { loadingRoutes.value = false }
})
</script>
