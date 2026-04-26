<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="$emit('close')">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] flex flex-col">

      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <div>
          <h2 class="text-lg font-semibold text-gray-900">{{ expense && !editing ? 'Détail' : expense ? 'Modifier' : 'Ajouter un frais' }}</h2>
          <p class="text-sm text-gray-500 mt-0.5">{{ formattedDate }}</p>
        </div>
        <button @click="$emit('close')" class="p-2 rounded-lg hover:bg-gray-100">
          <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <!-- Detail view -->
      <div v-if="expense && !editing" class="flex-1 overflow-y-auto px-6 py-5 space-y-1">
        <div class="flex items-center gap-2 mb-4">
          <span :class="['px-3 py-1 rounded-full text-sm font-medium', badgeClass(expense.type)]">
            {{ expenseIcon(expense.type) }} {{ expense.typeLabel }}
          </span>
        </div>
        <template v-if="expense.type === 'travel'">
          <InfoRow label="Type de véhicule" :value="vehicleTypeLabel((expense as TravelExpense).vehicleType)" />
          <InfoRow label="Départ" :value="(expense as TravelExpense).departure ?? '—'" />
          <InfoRow label="Arrivée" :value="(expense as TravelExpense).arrival ?? '—'" />
          <InfoRow label="Distance" :value="`${(expense as TravelExpense).distanceKm} km${(expense as TravelExpense).roundTrip ? ' × 2 (A/R)' : ''}`" />
          <InfoRow v-if="(expense as TravelExpense).vehicleType !== 'moped'" label="Puissance" :value="`${(expense as TravelExpense).vehiclePower} CV`" />
          <InfoRow v-if="(expense as TravelExpense).vehicleType === 'car'" label="Électrique" :value="(expense as TravelExpense).isElectric ? 'Oui (+20 %)' : 'Non'" />
          <InfoRow label="Aller-retour" :value="(expense as TravelExpense).roundTrip ? 'Oui' : 'Non'" />
        </template>
        <template v-else-if="expense.type === 'toll'">
          <InfoRow label="Départ" :value="(expense as TollExpense).departure ?? '—'" />
          <InfoRow label="Arrivée" :value="(expense as TollExpense).arrival ?? '—'" />
          <InfoRow label="Montant" :value="`${(expense as TollExpense).tollAmount.toFixed(2)} €`" />
        </template>
        <template v-else-if="expense.type === 'meal'">
          <InfoRow label="Montant du repas" :value="`${(expense as MealExpense).mealAmount.toFixed(2)} €`" />
          <InfoRow label="Valeur repas domicile" :value="`− ${(expense as MealExpense).homeMealValue.toFixed(2)} €`" />
          <InfoRow label="Montant déductible" :value="`${expense.amount.toFixed(2)} €`" />
        </template>
        <template v-else-if="expense.type === 'remote_work'">
          <InfoRow label="Indemnité" value="2,50 €" />
        </template>
        <InfoRow v-if="expense.description" label="Description" :value="expense.description" />
        <div class="flex gap-3 pt-4">
          <button @click="startEdit" class="flex-1 px-4 py-2 bg-indigo-50 border border-indigo-100 rounded-lg text-sm font-medium text-indigo-600 hover:bg-indigo-100">Modifier</button>
          <button @click="$emit('duplicate', expense)" class="flex-1 px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm font-medium text-gray-600 hover:bg-gray-100">Dupliquer</button>
          <button @click="confirmDelete" class="flex-1 px-4 py-2 bg-red-50 border border-red-100 rounded-lg text-sm font-medium text-red-600 hover:bg-red-100">Supprimer</button>
        </div>
      </div>

      <!-- Form -->
      <div v-else class="flex-1 overflow-y-auto px-6 py-5 space-y-5">
        <div v-if="!expense">
          <label class="block text-sm font-medium text-gray-700 mb-2">Type de frais</label>
          <div class="grid grid-cols-4 gap-2">
            <button v-for="t in expenseTypes" :key="t.value" @click="form.type = t.value"
              :class="['flex flex-col items-center gap-1.5 p-3 rounded-xl border-2 text-xs font-medium transition-all',
                form.type === t.value ? t.activeClass : 'border-gray-200 text-gray-600 hover:border-gray-300']">
              <span class="text-xl">{{ t.icon }}</span>{{ t.label }}
            </button>
          </div>
        </div>

        <template v-if="form.type === 'travel'">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Type de véhicule</label>
            <div class="grid grid-cols-3 gap-2">
              <button v-for="v in vehicleTypes" :key="v.value" @click="form.vehicleType = v.value"
                :class="['flex flex-col items-center gap-1 p-2.5 rounded-xl border-2 text-xs font-medium transition-all',
                  form.vehicleType === v.value ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-200 text-gray-600 hover:border-gray-300']">
                <span class="text-lg">{{ v.icon }}</span>{{ v.label }}
              </button>
            </div>
          </div>

          <!-- Favorite routes -->
          <div v-if="!expense && favorites.length" class="space-y-1.5">
            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wide">Trajets favoris</label>
            <div class="flex flex-wrap gap-2">
              <div v-for="fav in favorites" :key="fav.id" class="flex items-center">
                <button
                  @click="applyFavorite(fav)"
                  class="flex items-center gap-1.5 pl-2.5 pr-1.5 py-1 rounded-l-full border-2 border-indigo-200 bg-indigo-50 text-xs font-medium text-indigo-700 hover:bg-indigo-100 transition-colors"
                >
                  <span>⭐</span>
                  <span>{{ fav.name }}</span>
                </button>
                <button
                  @click="removeFavorite(fav.id)"
                  class="px-1.5 py-1 rounded-r-full border-2 border-l-0 border-indigo-200 bg-indigo-50 text-gray-400 hover:text-red-500 hover:bg-red-50 hover:border-red-200 transition-colors text-xs leading-none"
                  title="Supprimer ce favori"
                >×</button>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Départ</label><input v-model="form.departure" type="text" class="w-full rounded-lg border-gray-300 shadow-sm text-sm" placeholder="Adresse de départ" /></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Arrivée</label><input v-model="form.arrival" type="text" class="w-full rounded-lg border-gray-300 shadow-sm text-sm" placeholder="Adresse d'arrivée" /></div>
          </div>

          <div class="flex items-center gap-2">
            <button
              @click="calcDistance"
              :disabled="calculating || !form.departure || !form.arrival"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-300 bg-gray-50 text-xs font-medium text-gray-600 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            >
              <span v-if="calculating" class="animate-spin inline-block">⟳</span>
              <span v-else>📍</span>
              {{ calculating ? 'Calcul en cours…' : 'Calculer la distance' }}
            </button>
            <p v-if="calcError" class="text-xs text-red-600">{{ calcError }}</p>
          </div>

          <div :class="['grid gap-4', form.vehicleType !== 'moped' ? 'grid-cols-2' : 'grid-cols-1']">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Distance (km)</label>
              <input v-model.number="form.distanceKm" type="number" min="0" step="0.1" class="w-full rounded-lg border-gray-300 shadow-sm text-sm" placeholder="0" />
            </div>
            <div v-if="form.vehicleType !== 'moped'">
              <label class="block text-sm font-medium text-gray-700 mb-1.5">Puissance fiscale (CV)</label>
              <input v-model.number="form.vehiclePower" type="number" min="1" max="20" class="w-full rounded-lg border-gray-300 shadow-sm text-sm" placeholder="ex : 5" />
            </div>
          </div>

          <div class="flex items-center justify-between p-3 rounded-xl border border-gray-200 bg-gray-50">
            <div><p class="text-sm font-medium text-gray-700">Aller-retour</p><p class="text-xs text-gray-500 mt-0.5">La distance saisie sera multipliée par 2</p></div>
            <button type="button" @click="form.roundTrip = !form.roundTrip"
              :class="['relative inline-flex h-6 w-11 items-center rounded-full transition-colors', form.roundTrip ? 'bg-blue-600' : 'bg-gray-300']">
              <span :class="['inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform', form.roundTrip ? 'translate-x-6' : 'translate-x-1']" />
            </button>
          </div>

          <div v-if="form.vehicleType === 'car'" class="flex items-center justify-between p-3 rounded-xl border border-gray-200 bg-gray-50">
            <div><p class="text-sm font-medium text-gray-700">Véhicule électrique</p><p class="text-xs text-gray-500 mt-0.5">Majoration de +20 % sur l'indemnité calculée</p></div>
            <button type="button" @click="form.isElectric = !form.isElectric"
              :class="['relative inline-flex h-6 w-11 items-center rounded-full transition-colors', form.isElectric ? 'bg-emerald-600' : 'bg-gray-300']">
              <span :class="['inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform', form.isElectric ? 'translate-x-6' : 'translate-x-1']" />
            </button>
          </div>

          <!-- Save as favorite -->
          <div v-if="form.departure && form.arrival && !showSaveFavorite">
            <button @click="openSaveFavorite" class="text-xs text-indigo-500 hover:text-indigo-700 flex items-center gap-1">
              <span>⭐</span> Sauvegarder ce trajet en favori
            </button>
          </div>
          <div v-if="showSaveFavorite" class="flex gap-2 items-center">
            <input
              v-model="favoriteName"
              type="text"
              class="flex-1 rounded-lg border-gray-300 shadow-sm text-sm"
              placeholder="Nom du favori (ex : Domicile → Bureau)"
              @keyup.enter="confirmSaveFavorite"
              @keyup.escape="showSaveFavorite = false"
            />
            <button @click="confirmSaveFavorite" class="px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-medium hover:bg-indigo-700">Sauvegarder</button>
            <button @click="showSaveFavorite = false" class="px-2 py-1.5 border border-gray-200 rounded-lg text-xs text-gray-600 hover:bg-gray-50">✕</button>
          </div>
        </template>

        <template v-else-if="form.type === 'toll'">
          <div class="grid grid-cols-2 gap-4">
            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Départ</label><input v-model="form.departure" type="text" class="w-full rounded-lg border-gray-300 shadow-sm text-sm" placeholder="Optionnel" /></div>
            <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Arrivée</label><input v-model="form.arrival" type="text" class="w-full rounded-lg border-gray-300 shadow-sm text-sm" placeholder="Optionnel" /></div>
          </div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Montant (€)</label><input v-model.number="form.amount" type="number" min="0" step="0.01" class="w-full rounded-lg border-gray-300 shadow-sm text-sm" placeholder="0.00" /></div>
        </template>

        <template v-else-if="form.type === 'meal'">
          <div class="bg-orange-50 border border-orange-100 rounded-xl p-4 text-sm text-orange-700">
            <p class="font-medium">Repas professionnel 2024</p>
            <p class="mt-1 text-orange-600">5,35 € (valeur repas à domicile) seront déduits automatiquement du montant saisi.</p>
          </div>
          <div><label class="block text-sm font-medium text-gray-700 mb-1.5">Montant du repas (€)</label><input v-model.number="form.mealAmount" type="number" min="0" step="0.01" class="w-full rounded-lg border-gray-300 shadow-sm text-sm" placeholder="ex : 12.50" /></div>
          <p v-if="form.mealAmount > 5.35" class="text-xs text-emerald-600">Montant déductible : {{ (form.mealAmount - 5.35).toFixed(2) }} €</p>
          <p v-else-if="form.mealAmount > 0" class="text-xs text-amber-600">Montant inférieur à 5,35 € — non déductible.</p>
        </template>

        <template v-else>
          <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-4 text-sm text-emerald-700">
            <p class="font-medium">Indemnité télétravail 2024</p>
            <p class="mt-1 text-emerald-600">2,50 € par jour · Plafond 580 €/an (232 jours)</p>
          </div>
        </template>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Description (optionnel)</label>
          <input v-model="form.description" type="text" class="w-full rounded-lg border-gray-300 shadow-sm text-sm" placeholder="Motif, client…" />
        </div>
        <p v-if="error" class="text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">{{ error }}</p>
      </div>

      <div v-if="!expense || editing" class="px-6 py-4 border-t border-gray-200 flex gap-3">
        <button @click="editing ? cancelEdit() : $emit('close')" class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Annuler</button>
        <button @click="save" :disabled="saving" class="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 rounded-lg text-sm font-medium text-white">
          {{ saving ? 'Enregistrement…' : 'Enregistrer' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useExpenseStore } from '@/stores/expenseStore'
import { usePersonStore } from '@/stores/personStore'
import { useAuthStore } from '@/stores/authStore'
import type { Expense, TravelExpense, TollExpense, MealExpense, VehicleType } from '@/types'
import InfoRow from '@/components/ui/InfoRow.vue'
import { useRouteDistance } from '@/composables/useRouteDistance'
import { useFavoriteRoutes } from '@/composables/useFavoriteRoutes'

const props = defineProps<{ date: string; expense?: Expense | null; prefill?: Expense | null }>()
const emit = defineEmits<{ close: []; saved: []; duplicate: [expense: Expense] }>()

const expenseStore = useExpenseStore()
const personStore = usePersonStore()
const authStore = useAuthStore()
const editing = ref(false)
const saving = ref(false)
const error = ref('')

const { calculating, calcError, calculate } = useRouteDistance()
const { favorites, saveFavorite, removeFavorite } = useFavoriteRoutes()
const showSaveFavorite = ref(false)
const favoriteName = ref('')

function applyFavorite(fav: typeof favorites.value[number]) {
  form.value.departure = fav.departure
  form.value.arrival = fav.arrival
  form.value.vehicleType = fav.vehicleType
  form.value.vehiclePower = fav.vehiclePower
  form.value.isElectric = fav.isElectric
  form.value.roundTrip = fav.roundTrip
}

async function calcDistance() {
  const km = await calculate(form.value.departure, form.value.arrival)
  if (km !== null) form.value.distanceKm = km
}

function openSaveFavorite() {
  favoriteName.value = [form.value.departure, form.value.arrival].filter(Boolean).join(' → ')
  showSaveFavorite.value = true
}

function confirmSaveFavorite() {
  if (!favoriteName.value.trim()) return
  saveFavorite({
    name: favoriteName.value.trim(),
    departure: form.value.departure,
    arrival: form.value.arrival,
    vehicleType: form.value.vehicleType,
    vehiclePower: form.value.vehiclePower,
    isElectric: form.value.isElectric,
    roundTrip: form.value.roundTrip,
  })
  showSaveFavorite.value = false
  favoriteName.value = ''
}

const formattedDate = computed(() =>
  new Date(props.date + 'T12:00:00').toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
)

function buildForm(source?: Expense | null) {
  if (source?.type === 'travel') {
    const t = source as TravelExpense
    return { type: 'travel' as const, vehicleType: t.vehicleType, departure: t.departure ?? '', arrival: t.arrival ?? '', distanceKm: t.distanceKm, vehiclePower: t.vehiclePower ?? (authStore.user?.defaultFiscalPower ?? 5), amount: 0, mealAmount: 0, description: t.description ?? '', roundTrip: t.roundTrip, isElectric: t.isElectric }
  }
  if (source?.type === 'toll') {
    const t = source as TollExpense
    return { type: 'toll' as const, vehicleType: 'car' as VehicleType, departure: t.departure ?? '', arrival: t.arrival ?? '', distanceKm: 0, vehiclePower: authStore.user?.defaultFiscalPower ?? 5, amount: t.tollAmount, mealAmount: 0, description: t.description ?? '', roundTrip: false, isElectric: false }
  }
  if (source?.type === 'meal') {
    const m = source as MealExpense
    return { type: 'meal' as const, vehicleType: 'car' as VehicleType, departure: '', arrival: '', distanceKm: 0, vehiclePower: authStore.user?.defaultFiscalPower ?? 5, amount: 0, mealAmount: m.mealAmount, description: m.description ?? '', roundTrip: false, isElectric: false }
  }
  return {
    type: (source?.type ?? 'travel') as 'travel' | 'remote_work' | 'toll' | 'meal',
    vehicleType: 'car' as VehicleType,
    departure: '',
    arrival: '',
    distanceKm: 0,
    vehiclePower: authStore.user?.defaultFiscalPower ?? 5,
    amount: 0,
    mealAmount: 0,
    description: source?.description ?? '',
    roundTrip: false,
    isElectric: false,
  }
}

const form = ref(buildForm(props.prefill ?? props.expense))

function startEdit() {
  form.value = buildForm(props.expense)
  editing.value = true
}

function cancelEdit() {
  editing.value = false
}

const expenseTypes = [
  { value: 'travel' as const, label: 'Trajet', icon: '🚗', activeClass: 'border-blue-500 bg-blue-50 text-blue-700' },
  { value: 'remote_work' as const, label: 'Télétravail', icon: '🏠', activeClass: 'border-emerald-500 bg-emerald-50 text-emerald-700' },
  { value: 'toll' as const, label: 'Péage', icon: '🛣️', activeClass: 'border-amber-500 bg-amber-50 text-amber-700' },
  { value: 'meal' as const, label: 'Repas', icon: '🍽️', activeClass: 'border-orange-500 bg-orange-50 text-orange-700' },
]

const vehicleTypes = [
  { value: 'car' as VehicleType, label: 'Voiture', icon: '🚗' },
  { value: 'motorcycle' as VehicleType, label: 'Moto', icon: '🏍️' },
  { value: 'moped' as VehicleType, label: 'Cyclomoteur', icon: '🛵' },
]

function vehicleTypeLabel(vt: VehicleType): string {
  return ({ car: 'Voiture', motorcycle: 'Moto', moped: 'Cyclomoteur' } as Record<VehicleType, string>)[vt] ?? vt
}

function badgeClass(type: string) {
  return ({ travel: 'bg-blue-100 text-blue-700', remote_work: 'bg-emerald-100 text-emerald-700', toll: 'bg-amber-100 text-amber-700', meal: 'bg-orange-100 text-orange-700' } as Record<string, string>)[type] ?? ''
}
function expenseIcon(type: string) {
  return ({ travel: '🚗', remote_work: '🏠', toll: '🛣️', meal: '🍽️' } as Record<string, string>)[type] ?? '📌'
}

async function save() {
  error.value = ''
  if (!personStore.activePerson) { error.value = 'Sélectionnez une personne dans le menu.'; return }
  if (form.value.type === 'travel' && form.value.distanceKm <= 0) { error.value = 'Distance requise (> 0).'; return }
  if (form.value.type === 'toll' && form.value.amount <= 0) { error.value = 'Montant requis (> 0).'; return }
  if (form.value.type === 'meal' && form.value.mealAmount <= 0) { error.value = 'Montant requis (> 0).'; return }

  saving.value = true
  try {
    if (props.expense && editing.value) {
      // Mode édition
      const f = form.value
      if (f.type === 'travel') {
        await expenseStore.update(props.expense.id, { departure: f.departure || null, arrival: f.arrival || null, distanceKm: f.distanceKm, vehiclePower: f.vehicleType !== 'moped' ? f.vehiclePower : null, roundTrip: f.roundTrip, vehicleType: f.vehicleType, isElectric: f.vehicleType === 'car' ? f.isElectric : false, description: f.description || null })
      } else if (f.type === 'toll') {
        await expenseStore.update(props.expense.id, { amount: f.amount, departure: f.departure || null, arrival: f.arrival || null, description: f.description || null })
      } else if (f.type === 'meal') {
        await expenseStore.update(props.expense.id, { mealAmount: f.mealAmount, description: f.description || null })
      } else {
        await expenseStore.update(props.expense.id, { description: f.description || null })
      }
    } else {
      // Mode création
      const base = { personId: personStore.activePerson.id, date: props.date }
      if (form.value.type === 'travel') {
        await expenseStore.create({ ...base, type: 'travel', distanceKm: form.value.distanceKm, vehicleType: form.value.vehicleType, vehiclePower: form.value.vehicleType !== 'moped' ? form.value.vehiclePower : undefined, isElectric: form.value.vehicleType === 'car' ? form.value.isElectric : undefined, departure: form.value.departure || undefined, arrival: form.value.arrival || undefined, description: form.value.description || undefined, roundTrip: form.value.roundTrip })
      } else if (form.value.type === 'remote_work') {
        await expenseStore.create({ ...base, type: 'remote_work', description: form.value.description || undefined })
      } else if (form.value.type === 'toll') {
        await expenseStore.create({ ...base, type: 'toll', amount: form.value.amount, departure: form.value.departure || undefined, arrival: form.value.arrival || undefined, description: form.value.description || undefined })
      } else {
        await expenseStore.create({ ...base, type: 'meal', mealAmount: form.value.mealAmount, description: form.value.description || undefined })
      }
    }
    emit('saved')
  } catch { error.value = 'Une erreur est survenue.' }
  finally { saving.value = false }
}

async function confirmDelete() {
  if (!props.expense || !confirm('Supprimer ce frais ?')) return
  await expenseStore.remove(props.expense.id)
  emit('saved')
}
</script>
