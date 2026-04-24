<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="$emit('close')">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md">
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900">{{ person ? 'Modifier' : 'Nouvelle personne' }}</h2>
        <button @click="$emit('close')" class="p-2 rounded-lg hover:bg-gray-100">
          <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="px-6 py-5 space-y-4">
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
        <p v-if="error" class="text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">{{ error }}</p>
      </div>
      <div class="px-6 py-4 border-t border-gray-200 flex gap-3">
        <button @click="$emit('close')" class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Annuler</button>
        <button @click="save" :disabled="saving" class="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 rounded-lg text-sm font-medium text-white">
          {{ saving ? 'Enregistrement…' : 'Enregistrer' }}
        </button>
      </div>
    </div>
  </div>
</template>
<script setup lang="ts">
import { ref } from 'vue'
import { usePersonStore } from '@/stores/personStore'
import type { Person } from '@/types'

const props = defineProps<{ person?: Person | null }>()
const emit = defineEmits<{ close: []; saved: [] }>()
const store = usePersonStore()
const saving = ref(false)
const error = ref('')
const form = ref({ firstName: props.person?.firstName ?? '', lastName: props.person?.lastName ?? '', email: props.person?.email ?? '' })

async function save() {
  if (!form.value.firstName.trim() || !form.value.lastName.trim()) { error.value = 'Prénom et nom obligatoires.'; return }
  saving.value = true
  try {
    const data = { firstName: form.value.firstName.trim(), lastName: form.value.lastName.trim(), email: form.value.email.trim() || null }
    props.person ? await store.update(props.person.id, data) : await store.create(data)
    emit('saved')
  } catch { error.value = 'Une erreur est survenue.' }
  finally { saving.value = false }
}
</script>
