<template>
  <div class="workspace-page">
    <PageHeader eyebrow="Votre foyer fiscal" title="À chacun son espace." description="Profils, préférences et trajets habituels : tout commence ici.">
      <template #actions>
        <BaseButton icon="plus" @click="openCreate">Ajouter une personne</BaseButton>
      </template>
    </PageHeader>

    <div class="directory-toolbar">
      <div class="directory-search">
        <AppIcon name="search" />
        <label for="person-search" class="sr-only">Rechercher une personne</label>
        <input id="person-search" v-model="search" type="search" placeholder="Rechercher un nom ou un email…" autocomplete="off" />
      </div>
      <div class="segmented-control" role="group" aria-label="Filtrer les profils">
        <button type="button" @click="favoritesOnly = false" :class="{active:!favoritesOnly}" :aria-pressed="!favoritesOnly">Tous · {{ store.persons.length }}</button>
        <button type="button" @click="favoritesOnly = true" :class="{active:favoritesOnly}" :aria-pressed="favoritesOnly">Favoris · {{ store.persons.filter(p => p.favorite).length }}</button>
      </div>
    </div>

    <LoadingState v-if="store.loading" label="Chargement des profils…" />
    <AlertBanner v-else-if="store.error" variant="danger">
      {{ store.error }}
      <template #actions><BaseButton variant="secondary" size="sm" icon="refresh" @click="store.fetchAll">Réessayer</BaseButton></template>
    </AlertBanner>
    <EmptyState v-else-if="!store.persons.length" icon="users" title="Votre premier profil" description="Ajoutez une personne pour commencer à organiser ses frais.">
      <template #actions><BaseButton icon="plus" @click="openCreate">Créer un profil</BaseButton></template>
    </EmptyState>
    <EmptyState v-else-if="!visiblePersons.length" icon="search" title="Aucun profil trouvé" description="Essayez un autre nom ou affichez tous les profils." compact>
      <template #actions><BaseButton variant="secondary" @click="search = ''; favoritesOnly = false">Réinitialiser les filtres</BaseButton></template>
    </EmptyState>

    <div v-else class="person-directory">
      <article v-for="p in visiblePersons" :key="p.id" class="person-card" :class="{'is-current':store.activePerson?.id === p.id}">
        <div class="person-card-top">
          <span class="person-monogram" aria-hidden="true">{{ initials(p) }}</span>
          <button
            type="button"
            class="icon-button is-plain"
            :class="p.favorite ? 'text-amber-600' : 'text-ink-subtle'"
            @click="store.toggleFavorite(p.id)"
            :aria-label="p.favorite ? `Retirer ${p.fullName} des favoris` : `Ajouter ${p.fullName} aux favoris`"
            :aria-pressed="p.favorite"
          >
            <svg width="20" height="20" viewBox="0 0 24 24" :fill="p.favorite ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round" aria-hidden="true"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3 6.4 20.2l1.1-6.2L3 9.6l6.2-.9L12 3Z" /></svg>
          </button>
        </div>
        <h3>{{ p.fullName }}</h3>
        <p class="person-email">{{ p.email || 'Aucun email renseigné' }}</p>
        <div class="person-current">
          <span v-if="store.activePerson?.id === p.id" class="badge badge-success"><AppIcon name="check" :size="14" />Profil sélectionné</span>
          <BaseButton v-else variant="secondary" size="sm" @click="store.setActive(p)">Utiliser ce profil</BaseButton>
        </div>
        <button type="button" class="person-routes-link" @click="openEdit(p,'routes')">
          <span class="overview-icon blue" aria-hidden="true"><AppIcon name="repeat" /></span>
          <span><strong>Trajets habituels</strong><small>Gérer ses itinéraires favoris</small></span>
          <AppIcon name="chevron-right" :size="18" class="ml-auto text-ink-subtle" />
        </button>
        <footer>
          <BaseButton variant="ghost" size="sm" icon="edit" @click="openEdit(p)">Modifier</BaseButton>
          <BaseButton variant="ghost" size="sm" icon="trash" class="!text-danger-ink" @click="confirmRemove(p)" :aria-label="`Supprimer ${p.fullName}`">Supprimer</BaseButton>
        </footer>
      </article>
      <button type="button" class="add-person-card" @click="openCreate">
        <span aria-hidden="true"><AppIcon name="plus" :size="22" /></span>
        <strong>Un nouveau profil</strong>
        <small>Ajoutez un membre de votre foyer</small>
      </button>
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
import BaseButton from '@/components/ui/BaseButton.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LoadingState from '@/components/ui/LoadingState.vue'
import AlertBanner from '@/components/ui/AlertBanner.vue'
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
