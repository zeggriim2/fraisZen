<template>
  <div class="workspace-page">
    <div class="workspace-title"><div><span class="eyebrow">VOTRE FOYER FISCAL</span><h2>À chacun son espace.</h2><p>Profils, préférences et trajets habituels : tout commence ici.</p></div><button class="primary-action" @click="openCreate">＋ Ajouter une personne</button></div>
    <div class="directory-toolbar"><div class="directory-search"><AppIcon name="users" /><input v-model="search" aria-label="Rechercher une personne" placeholder="Rechercher un nom ou un email…" /></div><div class="segmented-control"><button @click="favoritesOnly = false" :class="{active:!favoritesOnly}" :aria-pressed="!favoritesOnly">Tous · {{ store.persons.length }}</button><button @click="favoritesOnly = true" :class="{active:favoritesOnly}" :aria-pressed="favoritesOnly">Favoris · {{ store.persons.filter(p => p.favorite).length }}</button></div></div>
    <p v-if="store.loading" role="status" class="empty-workspace">Chargement des profils…</p>
    <p v-else-if="store.error" role="alert" class="empty-workspace text-red-700">{{ store.error }} <button class="quiet-button" @click="store.fetchAll">Réessayer</button></p>
    <div v-else-if="!store.persons.length" class="empty-workspace"><AppIcon name="users" /><h3>Votre premier profil</h3><p>Ajoutez une personne pour commencer à organiser ses frais.</p><button class="primary-action" @click="openCreate">Créer un profil ↗</button></div>
    <div v-else-if="!visiblePersons.length" class="empty-workspace"><h3>Aucun profil trouvé</h3><p>Essayez un autre nom ou affichez tous les profils.</p><button class="quiet-button" @click="search = ''; favoritesOnly = false">Réinitialiser les filtres</button></div>
    <div v-else class="person-directory">
      <article v-for="p in visiblePersons" :key="p.id" class="person-card" :class="{'is-current':store.activePerson?.id === p.id}">
        <div class="person-card-top"><span class="person-monogram">{{ initials(p) }}</span><button class="icon-button" @click="store.toggleFavorite(p.id)" :aria-label="p.favorite ? `Retirer ${p.fullName} des favoris` : `Ajouter ${p.fullName} aux favoris`" :aria-pressed="p.favorite"><span :class="p.favorite ? 'text-amber-600' : 'text-gray-400'">{{ p.favorite ? '★' : '☆' }}</span></button></div>
        <h3>{{ p.fullName }}</h3><p class="person-email">{{ p.email || 'Aucun email renseigné' }}</p>
        <div class="person-current"><span v-if="store.activePerson?.id === p.id" class="status-pill"><span class="category-dot bg-emerald-500" />Profil sélectionné</span><button v-else class="quiet-button" @click="store.setActive(p)">Utiliser ce profil ↗</button></div>
        <button class="person-routes-link" @click="openEdit(p,'routes')"><span class="overview-icon blue"><AppIcon name="repeat" /></span><span><strong>Trajets habituels</strong><small>Gérer ses itinéraires favoris</small></span><span class="ml-auto">↗</span></button>
        <footer><button @click="openEdit(p)">Modifier le profil</button><button class="text-red-700" @click="confirmRemove(p)" :aria-label="`Supprimer ${p.fullName}`">Supprimer</button></footer>
      </article>
      <button class="add-person-card" @click="openCreate"><span>＋</span><strong>Un nouveau profil</strong><small>Ajoutez un membre de votre foyer</small></button>
    </div>
    <section v-if="!store.loading && store.persons.length" class="mt-8" aria-labelledby="household-tax-comparison-title">
      <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
        <div><h3 id="household-tax-comparison-title" class="text-base font-semibold text-gray-900">{{ t('tax.comparison.dashboard.title') }}</h3><p class="text-sm text-gray-500">{{ t('tax.comparison.dashboard.subtitle') }}</p></div>
        <label class="text-sm text-gray-600 flex items-center gap-2">{{ t('tax.comparison.dashboard.year') }}<select v-model.number="comparisonYear" class="rounded-lg border-gray-300 shadow-sm text-sm"><option v-for="y in comparisonYears" :key="y" :value="y">{{ y }}</option></select></label>
      </div>
      <!-- Une carte indépendante par personne : aucun écart n'est additionné ni comparé entre personnes.
           Sous chaque carte, les données fiscales de CETTE personne (cibles des liens « Compléter ce champ »). -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div v-for="p in store.persons" :key="p.id" class="flex flex-col gap-3">
          <TaxComparisonCard :person="p" :year="comparisonYear" :show-person-name="true" :reload-token="reloadTokens[p.id] ?? 0" />
          <TaxInputsPanel :person="p" :year="comparisonYear" :focus-field-id="focusTarget?.personId === p.id ? focusTarget.fieldId : null" @changed="reloadTokens[p.id] = (reloadTokens[p.id] ?? 0) + 1" />
        </div>
      </div>
    </section>
    <PersonModal v-if="showModal" :person="editing" :initial-tab="initialTab" @close="closeModal" @saved="onSaved" />

    <ConfirmModal
      v-if="pendingRemove"
      title="Supprimer cette personne ?"
      :message="`${pendingRemove.fullName} et tous ses frais associés seront définitivement supprimés.`"
      confirm-label="Supprimer"
      cancel-label="Annuler"
      variant="danger"
      :icon="true"
      @confirm="doRemove"
      @cancel="pendingRemove = null"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, reactive, watch } from 'vue'
import { usePersonStore } from '@/stores/personStore'
import type { Person } from '@/types'
import AppIcon from '@/components/ui/AppIcon.vue'
import PersonModal from '@/components/person/PersonModal.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'
import TaxComparisonCard from '@/components/tax/TaxComparisonCard.vue'
import TaxInputsPanel from '@/components/tax/TaxInputsPanel.vue'
import { fieldIdFromHash } from '@/utils/fieldAnchor'
import { t } from '@/i18n'
import { useRoute } from 'vue-router'

const store = usePersonStore()
const route = useRoute()
const currentYear = new Date().getFullYear()
// Jan–juin : campagne de déclaration de l'année N-1 ; ensuite, année en cours. Un lien direct (?year=) prime.
const requestedYear = Number(route.query.year)
const comparisonYears = Array.from({ length: 6 }, (_, i) => currentYear - i)
const comparisonYear = ref(comparisonYears.includes(requestedYear) ? requestedYear : (new Date().getMonth() < 6 ? currentYear - 1 : currentYear))
const reloadTokens = reactive<Record<string, number>>({})
// Direct link from a comparison (?personId=&year=#tax-field-…): opens that person's panel on the field.
const focusTarget = ref<{ personId: string; fieldId: string } | null>(null)
watch(() => route.fullPath, () => {
  const year = Number(route.query.year)
  if (comparisonYears.includes(year)) comparisonYear.value = year
  const personId = typeof route.query.personId === 'string' ? route.query.personId : null
  const fieldId = fieldIdFromHash(route.hash)
  focusTarget.value = personId && fieldId ? { personId, fieldId } : null
}, { immediate: true })
const search = ref('')
const favoritesOnly = ref(false)
const initialTab = ref<'identity' | 'routes'>('identity')
const visiblePersons = computed(() => store.persons.filter(p => (!favoritesOnly.value || p.favorite) && `${p.fullName} ${p.email ?? ''}`.toLocaleLowerCase('fr').includes(search.value.toLocaleLowerCase('fr'))))
const showModal = ref(false)
const editing = ref<Person | null>(null)
const pendingRemove = ref<Person | null>(null)

const initials = (p: Person) => (p.firstName[0] + p.lastName[0]).toUpperCase()
const fmt = (d: string) => new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })

function openCreate() { initialTab.value = 'identity'; editing.value = null; showModal.value = true }
function openEdit(p: Person, tab: 'identity' | 'routes' = 'identity') { initialTab.value = tab; editing.value = p; showModal.value = true }
function closeModal() { showModal.value = false; editing.value = null }
async function onSaved() { closeModal(); await store.fetchAll() }
function confirmRemove(p: Person) { pendingRemove.value = p }
async function doRemove() {
  if (!pendingRemove.value) return
  await store.remove(pendingRemove.value.id)
  pendingRemove.value = null
}
</script>
