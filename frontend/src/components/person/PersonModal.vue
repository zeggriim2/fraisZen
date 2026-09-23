<template>
  <BaseModal max-width="wide" @close="$emit('close')">
    <template #title>{{ person ? person.fullName : 'Nouvelle personne' }}</template>

    <div v-if="person" class="profile-tabs"><button @click="activeTab = 'identity'" :class="{active:activeTab === 'identity'}" :aria-pressed="activeTab === 'identity'"><AppIcon name="users" />Informations du profil</button><button @click="activeTab = 'routes'" :class="{active:activeTab === 'routes'}" :aria-pressed="activeTab === 'routes'"><AppIcon name="repeat" />Trajets habituels</button></div>
    <div class="person-editor space-y-4">
      <section v-show="activeTab === 'identity'" class="profile-identity space-y-4"><div class="form-intro"><span class="eyebrow">INFORMATIONS PERSONNELLES</span><h3>Un profil à votre image.</h3><p>Ces informations identifient la personne associée aux frais.</p></div>
        <!-- Identity fields -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Prénom *</label>
            <input v-model="form.firstName" type="text" class="w-full rounded-lg border-gray-300 shadow-sm text-sm" placeholder="Jean" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nom *</label>
            <input v-model="form.lastName" type="text" class="w-full rounded-lg border-gray-300 shadow-sm text-sm" placeholder="Dupont" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Email (optionnel)</label>
          <input v-model="form.email" type="email" class="w-full rounded-lg border-gray-300 shadow-sm text-sm" placeholder="jean@email.fr" />
        </div>

        <!-- Favorite toggle -->
        <div class="flex items-center justify-between p-3 rounded-xl border border-gray-200 bg-gray-50">
          <div>
            <p class="text-sm font-medium text-gray-700">Favori</p>
            <p class="text-xs text-gray-500 mt-0.5">S'affiche en priorité dans toutes les listes</p>
          </div>
          <button
            type="button"
            @click="form.favorite = !form.favorite" :aria-pressed="form.favorite" aria-label="Profil favori"
            :class="['text-2xl leading-none transition-colors', form.favorite ? 'text-yellow-400 hover:text-yellow-500' : 'text-gray-300 hover:text-yellow-300']"
          >★</button>
        </div>

      </section>
        <!-- Favorite routes (edit mode only) -->
        <div v-if="person" v-show="activeTab === 'routes'" class="route-manager space-y-3">
          <div class="flex items-center justify-between">
            <div ref="routeHeading" tabindex="-1" class="form-intro"><span class="eyebrow">VOS ITINÉRAIRES</span><h3>Moins de saisie, plus de simplicité.</h3><p>Réutilisez ces trajets lors de vos déclarations de frais.</p></div>
            <button @click="showAddRoute = !showAddRoute; editingRouteId = null; routeError = ''" class="quiet-button">
              {{ showAddRoute ? '✕ Annuler' : '+ Ajouter' }}
            </button>
          </div>

          <!-- Add route form -->
          <div v-if="showAddRoute" class="p-3 rounded-xl border border-indigo-100 bg-indigo-50/40 space-y-3">
            <div>
              <input v-model="newRoute.name" type="text" class="w-full rounded-lg border-gray-300 shadow-sm text-sm" placeholder="Nom (ex : Domicile → Bureau)" />
            </div>
            <div class="grid grid-cols-2 gap-2">
              <AddressAutocompleteInput v-model="newRoute.departure" placeholder="Départ" />
              <AddressAutocompleteInput v-model="newRoute.arrival" placeholder="Arrivée" />
            </div>
            <div class="grid grid-cols-3 gap-1.5">
              <button v-for="v in vehicleTypes" :key="v.value" @click="newRoute.vehicleType = v.value"
                :class="['flex flex-col items-center gap-0.5 p-2 rounded-lg border-2 text-xs font-medium transition-all',
                  newRoute.vehicleType === v.value ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-200 text-gray-600 hover:border-gray-300']">
                <span>{{ v.icon }}</span>{{ v.label }}
              </button>
            </div>
            <div v-if="newRoute.vehicleType !== 'moped'" class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-xs text-gray-600 mb-1">Puissance (CV)</label>
                <input v-model.number="newRoute.vehiclePower" type="number" min="1" max="20" class="w-full rounded-lg border-gray-300 shadow-sm text-sm" placeholder="5" />
              </div>
              <div v-if="newRoute.vehicleType === 'car'" class="flex items-end pb-0.5">
                <label class="flex items-center gap-2 cursor-pointer text-xs text-gray-700">
                  <button type="button" @click="newRoute.isElectric = !newRoute.isElectric" role="switch" :aria-checked="newRoute.isElectric" aria-label="Véhicule électrique"
                    :class="['relative inline-flex h-5 w-9 items-center rounded-full transition-colors shrink-0', newRoute.isElectric ? 'bg-emerald-500' : 'bg-gray-300']">
                    <span :class="['inline-block h-3 w-3 transform rounded-full bg-white shadow transition-transform', newRoute.isElectric ? 'translate-x-5' : 'translate-x-1']" />
                  </button>
                  Électrique
                </label>
              </div>
            </div>
            <div class="flex items-center justify-between">
              <label class="flex items-center gap-2 cursor-pointer text-xs text-gray-700">
                <button type="button" @click="newRoute.roundTrip = !newRoute.roundTrip" role="switch" :aria-checked="newRoute.roundTrip" aria-label="Aller-retour"
                  :class="['relative inline-flex h-5 w-9 items-center rounded-full transition-colors shrink-0', newRoute.roundTrip ? 'bg-blue-500' : 'bg-gray-300']">
                  <span :class="['inline-block h-3 w-3 transform rounded-full bg-white shadow transition-transform', newRoute.roundTrip ? 'translate-x-5' : 'translate-x-1']" />
                </button>
                Aller-retour
              </label>
              <button @click="saveNewRoute" :disabled="savingRoute" class="px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-medium hover:bg-indigo-700 disabled:opacity-50">
                {{ savingRoute ? '…' : 'Sauvegarder' }}
              </button>
            </div>
            <p v-if="routeError" class="text-xs text-red-600">{{ routeError }}</p>
          </div>

          <!-- Routes list -->
          <div v-if="routeStore.loading" class="text-xs text-gray-400 py-1">Chargement…</div>
          <div v-else-if="routeStore.routes.length" class="space-y-1.5">
            <template v-for="route in routeStore.routes" :key="route.id">

              <!-- Edit form (inline) -->
              <div v-if="editingRouteId === route.id" class="p-3 rounded-xl border border-blue-100 bg-blue-50/40 space-y-3">
                <div>
                  <input v-model="editForm.name" type="text" class="w-full rounded-lg border-gray-300 shadow-sm text-sm" placeholder="Nom du trajet" />
                </div>
                <div class="grid grid-cols-2 gap-2">
                  <AddressAutocompleteInput v-model="editForm.departure" placeholder="Départ" />
                  <AddressAutocompleteInput v-model="editForm.arrival" placeholder="Arrivée" />
                </div>
                <div class="grid grid-cols-3 gap-1.5">
                  <button v-for="v in vehicleTypes" :key="v.value" @click="editForm.vehicleType = v.value"
                    :class="['flex flex-col items-center gap-0.5 p-2 rounded-lg border-2 text-xs font-medium transition-all',
                      editForm.vehicleType === v.value ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-200 text-gray-600 hover:border-gray-300']">
                    <span>{{ v.icon }}</span>{{ v.label }}
                  </button>
                </div>
                <div v-if="editForm.vehicleType !== 'moped'" class="grid grid-cols-2 gap-2">
                  <div>
                    <label class="block text-xs text-gray-600 mb-1">Puissance (CV)</label>
                    <input v-model.number="editForm.vehiclePower" type="number" min="1" max="20" class="w-full rounded-lg border-gray-300 shadow-sm text-sm" placeholder="5" />
                  </div>
                  <div v-if="editForm.vehicleType === 'car'" class="flex items-end pb-0.5">
                    <label class="flex items-center gap-2 cursor-pointer text-xs text-gray-700">
                      <button type="button" @click="editForm.isElectric = !editForm.isElectric" role="switch" :aria-checked="editForm.isElectric" aria-label="Véhicule électrique"
                        :class="['relative inline-flex h-5 w-9 items-center rounded-full transition-colors shrink-0', editForm.isElectric ? 'bg-emerald-500' : 'bg-gray-300']">
                        <span :class="['inline-block h-3 w-3 transform rounded-full bg-white shadow transition-transform', editForm.isElectric ? 'translate-x-5' : 'translate-x-1']" />
                      </button>
                      Électrique
                    </label>
                  </div>
                </div>
                <div class="flex items-center justify-between">
                  <label class="flex items-center gap-2 cursor-pointer text-xs text-gray-700">
                    <button type="button" @click="editForm.roundTrip = !editForm.roundTrip" role="switch" :aria-checked="editForm.roundTrip" aria-label="Aller-retour"
                      :class="['relative inline-flex h-5 w-9 items-center rounded-full transition-colors shrink-0', editForm.roundTrip ? 'bg-blue-500' : 'bg-gray-300']">
                      <span :class="['inline-block h-3 w-3 transform rounded-full bg-white shadow transition-transform', editForm.roundTrip ? 'translate-x-5' : 'translate-x-1']" />
                    </button>
                    Aller-retour
                  </label>
                  <div class="flex gap-2">
                    <button @click="cancelEdit" class="px-3 py-1.5 border border-gray-200 rounded-lg text-xs text-gray-600 hover:bg-gray-50">Annuler</button>
                    <button @click="saveEditRoute(route.id)" :disabled="savingRoute" class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-medium hover:bg-blue-700 disabled:opacity-50">
                      {{ savingRoute ? '…' : 'Sauvegarder' }}
                    </button>
                  </div>
                </div>
                <p v-if="routeError" class="text-xs text-red-600">{{ routeError }}</p>
              </div>

              <!-- Display row -->
              <div v-else class="saved-route-card">
                <span class="overview-icon blue"><AppIcon name="car" /></span>
                <div class="flex-1 min-w-0">
                  <p class="text-xs font-medium text-gray-800 truncate">{{ route.name }}</p>
                  <p class="route-itinerary"><span>{{ route.departure }}</span><span aria-hidden="true">→</span><span>{{ route.arrival }}</span></p><div class="route-tags"><span>{{ route.roundTrip ? 'Aller-retour' : 'Aller simple' }}</span><span>{{ vehicleTypes.find(v => v.value === route.vehicleType)?.label }}</span><span v-if="route.vehiclePower">{{ route.vehiclePower }} CV</span><span v-if="route.isElectric">Électrique</span></div>
                </div>
                <span class="text-xs text-gray-400 shrink-0">{{ vehicleIcon(route.vehicleType, route.isElectric) }}</span>
                <button @click="startEdit(route)" class="p-0.5 text-gray-600 hover:text-blue-500 shrink-0" title="Modifier">
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                <button @click="deleteRoute(route.id)" class="p-0.5 text-gray-600 hover:text-red-500 shrink-0" title="Supprimer">
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
              </div>

            </template>
          </div>
          <p v-else-if="!showAddRoute" class="route-empty">Aucun trajet enregistré. Ajoutez votre premier itinéraire habituel.</p>
        </div>

        <div v-else class="text-xs text-gray-400 pt-1">
          Les trajets favoris seront disponibles après la création de la personne.
        </div>

        <p v-if="routeError && !showAddRoute && !editingRouteId" role="alert" class="text-sm text-red-700">{{ routeError }}</p>
        <p v-if="error" class="text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">{{ error }}</p>
    </div>

    <template #footer>
      <div class="flex gap-3">
        <button @click="$emit('close')" class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Fermer</button>
        <button v-if="activeTab === 'identity'" @click="save" :disabled="saving" class="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 rounded-lg text-sm font-medium text-white">
          {{ saving ? 'Enregistrement…' : 'Enregistrer' }}
        </button>
      </div>
    </template>
  </BaseModal>
</template>

<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue'
import { usePersonStore } from '@/stores/personStore'
import { useFavoriteRouteStore } from '@/stores/favoriteRouteStore'
import type { Person, VehicleType } from '@/types'
import AddressAutocompleteInput from '@/components/ui/AddressAutocompleteInput.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseModal from '@/components/ui/BaseModal.vue'

const props = defineProps<{ person?: Person | null; initialTab?: 'identity' | 'routes' }>()
const emit = defineEmits<{ close: []; saved: [] }>()

const personStore = usePersonStore()
const routeStore = useFavoriteRouteStore()

const activeTab = ref<'identity' | 'routes'>(props.person ? props.initialTab ?? 'identity' : 'identity')
const saving = ref(false)
const error = ref('')
const form = ref({
  firstName: props.person?.firstName ?? '',
  lastName: props.person?.lastName ?? '',
  email: props.person?.email ?? '',
  favorite: props.person?.favorite ?? false,
})

// Route management
const routeHeading = ref<HTMLElement | null>(null)
const showAddRoute = ref(false)
const savingRoute = ref(false)
const routeError = ref('')
const editingRouteId = ref<string | null>(null)

function freshRoute() {
  return { name: '', departure: '', arrival: '', vehicleType: 'car' as VehicleType, vehiclePower: 5, isElectric: false, roundTrip: false }
}
const newRoute = ref(freshRoute())
const editForm = ref(freshRoute())

const vehicleTypes = [
  { value: 'car' as VehicleType, label: 'Voiture', icon: '🚗' },
  { value: 'motorcycle' as VehicleType, label: 'Moto', icon: '🏍️' },
  { value: 'moped' as VehicleType, label: 'Cyclomoteur', icon: '🛵' },
]

function vehicleIcon(type: string, isElectric = false): string {
  if (type === 'car' && isElectric) return '⚡'
  return ({ car: '🚗', motorcycle: '🏍️', moped: '🛵' } as Record<string, string>)[type] ?? '🚗'
}

onMounted(async () => {
  if (props.person) { routeStore.reset(); try { await routeStore.fetchByPerson(props.person.id) } catch { routeError.value = 'Impossible de charger les trajets.' } }
})

async function saveNewRoute() {
  routeError.value = ''
  if (!newRoute.value.name.trim()) { routeError.value = 'Nom requis.'; return }
  if (!newRoute.value.departure.trim() || !newRoute.value.arrival.trim()) { routeError.value = 'Départ et arrivée requis.'; return }
  if (!props.person) return
  savingRoute.value = true
  try {
    await routeStore.create(props.person.id, {
      name: newRoute.value.name.trim(),
      departure: newRoute.value.departure.trim(),
      arrival: newRoute.value.arrival.trim(),
      vehicleType: newRoute.value.vehicleType,
      vehiclePower: newRoute.value.vehicleType !== 'moped' ? newRoute.value.vehiclePower : null,
      isElectric: newRoute.value.vehicleType === 'car' ? newRoute.value.isElectric : false,
      roundTrip: newRoute.value.roundTrip,
    })
    newRoute.value = freshRoute()
    showAddRoute.value = false
    await nextTick(); routeHeading.value?.focus()
  } catch {
    routeError.value = 'Une erreur est survenue.'
  } finally {
    savingRoute.value = false
  }
}

function startEdit(route: typeof routeStore.routes[number]) {
  showAddRoute.value = false
  editingRouteId.value = route.id
  routeError.value = ''
  editForm.value = {
    name: route.name,
    departure: route.departure,
    arrival: route.arrival,
    vehicleType: route.vehicleType,
    vehiclePower: route.vehiclePower ?? 5,
    isElectric: route.isElectric,
    roundTrip: route.roundTrip,
  }
}

function cancelEdit() {
  editingRouteId.value = null
  routeError.value = ''
}

async function saveEditRoute(id: string) {
  routeError.value = ''
  if (!editForm.value.name.trim()) { routeError.value = 'Nom requis.'; return }
  if (!editForm.value.departure.trim() || !editForm.value.arrival.trim()) { routeError.value = 'Départ et arrivée requis.'; return }
  if (!props.person) return
  savingRoute.value = true
  try {
    await routeStore.update(props.person.id, id, {
      name: editForm.value.name.trim(),
      departure: editForm.value.departure.trim(),
      arrival: editForm.value.arrival.trim(),
      vehicleType: editForm.value.vehicleType,
      vehiclePower: editForm.value.vehicleType !== 'moped' ? editForm.value.vehiclePower : null,
      isElectric: editForm.value.vehicleType === 'car' ? editForm.value.isElectric : false,
      roundTrip: editForm.value.roundTrip,
    })
    editingRouteId.value = null
    await nextTick(); routeHeading.value?.focus()
  } catch {
    routeError.value = 'Une erreur est survenue.'
  } finally {
    savingRoute.value = false
  }
}

async function deleteRoute(id: string) {
  if (!props.person) return
  try { await routeStore.remove(props.person.id, id) } catch { routeError.value = 'Impossible de supprimer ce trajet.' }
}

async function save() {
  if (!form.value.firstName.trim() || !form.value.lastName.trim()) { error.value = 'Prénom et nom obligatoires.'; return }
  saving.value = true
  try {
    const data = {
      firstName: form.value.firstName.trim(),
      lastName: form.value.lastName.trim(),
      email: form.value.email.trim() || null,
      favorite: form.value.favorite,
    }
    props.person ? await personStore.update(props.person.id, data) : await personStore.create(data)
    emit('saved')
  } catch { error.value = 'Une erreur est survenue.' }
  finally { saving.value = false }
}
</script>
