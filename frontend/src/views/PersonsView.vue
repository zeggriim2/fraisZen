<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-semibold text-gray-900">Personnes</h2>
      <button @click="openCreate" class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
        + Ajouter
      </button>
    </div>

    <div v-if="store.persons.length === 0" class="text-center py-16">
      <p class="text-4xl mb-3">👤</p>
      <p class="text-gray-500 text-sm">Aucune personne. Commencez par en ajouter une.</p>
    </div>

    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="p in store.persons" :key="p.id" class="bg-white rounded-2xl border border-gray-200 p-5 shadow-sm">
        <div class="flex items-start justify-between mb-3">
          <div class="w-12 h-12 rounded-xl bg-indigo-100 flex items-center justify-center text-xl font-bold text-indigo-600">
            {{ initials(p) }}
          </div>
          <div class="flex gap-1">
            <button @click="openEdit(p)" class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </button>
            <button @click="remove(p.id)" class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </button>
          </div>
        </div>
        <h3 class="font-semibold text-gray-900">{{ p.fullName }}</h3>
        <p v-if="p.email" class="text-sm text-gray-500 mt-0.5">{{ p.email }}</p>
        <p class="text-xs text-gray-400 mt-2">Depuis le {{ fmt(p.createdAt) }}</p>
      </div>
    </div>

    <PersonModal v-if="showModal" :person="editing" @close="closeModal" @saved="onSaved" />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { usePersonStore } from '@/stores/personStore'
import type { Person } from '@/types'
import PersonModal from '@/components/person/PersonModal.vue'

const store = usePersonStore()
const showModal = ref(false)
const editing = ref<Person | null>(null)

const initials = (p: Person) => (p.firstName[0] + p.lastName[0]).toUpperCase()
const fmt = (d: string) => new Date(d).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })

function openCreate() { editing.value = null; showModal.value = true }
function openEdit(p: Person) { editing.value = p; showModal.value = true }
function closeModal() { showModal.value = false; editing.value = null }
async function onSaved() { closeModal(); await store.fetchAll() }
async function remove(id: string) {
  if (!confirm('Supprimer cette personne et tous ses frais ?')) return
  await store.remove(id)
}
</script>
