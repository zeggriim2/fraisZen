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
import { ref, computed } from 'vue'
import { usePersonStore } from '@/stores/personStore'
import type { Person } from '@/types'
import AppIcon from '@/components/ui/AppIcon.vue'
import PersonModal from '@/components/person/PersonModal.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'

const store = usePersonStore()
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
