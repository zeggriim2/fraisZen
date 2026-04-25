<template>
  <div class="p-6">
    <div class="flex items-center gap-3 mb-6">
      <RouterLink to="/admin/users" class="text-gray-400 hover:text-gray-200 text-sm">← Retour</RouterLink>
      <span class="text-gray-600">/</span>
      <h2 class="text-xl font-bold text-gray-100">{{ user?.email }}</h2>
    </div>

    <div v-if="loading" class="text-gray-400 text-sm">Chargement…</div>

    <div v-else-if="user" class="space-y-6">
      <!-- Compte -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 p-5">
        <h3 class="text-sm font-semibold text-gray-300 mb-4">Informations du compte</h3>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <p class="text-xs text-gray-500 mb-1">Email</p>
            <p class="text-gray-200">{{ user.email }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500 mb-1">Inscription</p>
            <p class="text-gray-200">{{ formatDate(user.createdAt) }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500 mb-1">Rôles</p>
            <p class="text-gray-200">{{ user.roles.join(', ') }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-500 mb-1">Année fiscale par défaut</p>
            <p class="text-gray-200">{{ user.defaultYear ?? '—' }}</p>
          </div>
        </div>
      </div>

      <!-- Abonnement -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 p-5">
        <h3 class="text-sm font-semibold text-gray-300 mb-4">Abonnement</h3>
        <div class="flex items-end gap-4">
          <div>
            <p class="text-xs text-gray-500 mb-1">Statut actuel</p>
            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statusClass(user.subscriptionStatus)]">
              {{ statusLabel(user.subscriptionStatus) }}
            </span>
          </div>
          <div class="flex-1">
            <label class="text-xs text-gray-500 block mb-1">Modifier le statut</label>
            <select v-model="newStatus" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-sm text-gray-200 focus:outline-none focus:border-indigo-500">
              <option value="active">Actif</option>
              <option value="canceled">Annulé</option>
              <option value="inactive">Inactif</option>
            </select>
          </div>
          <button
            @click="saveStatus"
            :disabled="savingStatus"
            class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-500 disabled:opacity-50 transition-colors"
          >
            {{ savingStatus ? 'Enregistrement…' : 'Enregistrer' }}
          </button>
        </div>
        <p v-if="statusSaved" class="text-xs text-emerald-400 mt-2">Statut mis à jour.</p>
      </div>

      <!-- Personnes -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 p-5">
        <h3 class="text-sm font-semibold text-gray-300 mb-4">Personnes ({{ user.persons.length }})</h3>
        <div v-if="user.persons.length === 0" class="text-gray-500 text-sm">Aucune personne</div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="border-b border-gray-700">
              <th class="text-left pb-2 text-xs text-gray-500">Nom</th>
              <th class="text-left pb-2 text-xs text-gray-500">Email</th>
              <th class="text-left pb-2 text-xs text-gray-500">Dépenses</th>
              <th class="text-left pb-2 text-xs text-gray-500">Créé le</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in user.persons" :key="p.id" class="border-b border-gray-700/50">
              <td class="py-2 text-gray-200">{{ p.fullName }}</td>
              <td class="py-2 text-gray-400">{{ p.email ?? '—' }}</td>
              <td class="py-2 text-gray-400">{{ p.expenseCount }}</td>
              <td class="py-2 text-gray-400">{{ formatDate(p.createdAt) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Actions -->
      <div class="bg-gray-800 rounded-xl border border-gray-700 p-5">
        <h3 class="text-sm font-semibold text-gray-300 mb-4">Actions</h3>
        <div class="flex gap-3">
          <button
            @click="impersonate"
            :disabled="impersonating"
            class="px-4 py-2 bg-gray-700 text-gray-200 text-sm rounded-lg hover:bg-gray-600 disabled:opacity-50 transition-colors"
          >
            {{ impersonating ? 'Génération…' : '🔑 Se connecter en tant que cet utilisateur' }}
          </button>
          <button
            @click="confirmDelete"
            class="px-4 py-2 bg-red-900/50 text-red-400 text-sm rounded-lg hover:bg-red-900 transition-colors"
          >
            Supprimer le compte
          </button>
        </div>
        <p v-if="impersonateError" class="text-xs text-red-400 mt-2">{{ impersonateError }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { adminApi, type AdminUserDetail } from '@/api/adminApi'

const route = useRoute()
const router = useRouter()
const id = route.params.id as string

const user = ref<AdminUserDetail | null>(null)
const loading = ref(true)
const newStatus = ref('')
const savingStatus = ref(false)
const statusSaved = ref(false)
const impersonating = ref(false)
const impersonateError = ref('')

async function load() {
  loading.value = true
  try {
    user.value = await adminApi.getUser(id)
    newStatus.value = user.value.subscriptionStatus ?? 'inactive'
  } finally {
    loading.value = false
  }
}

async function saveStatus() {
  savingStatus.value = true
  statusSaved.value = false
  try {
    await adminApi.updateSubscription(id, newStatus.value)
    if (user.value) user.value.subscriptionStatus = newStatus.value
    statusSaved.value = true
    setTimeout(() => { statusSaved.value = false }, 2000)
  } finally {
    savingStatus.value = false
  }
}

async function impersonate() {
  impersonating.value = true
  impersonateError.value = ''
  try {
    const { token } = await adminApi.impersonate(id)
    const url = new URL(window.location.origin)
    url.pathname = '/calendar'
    url.searchParams.set('impersonate_token', token)
    window.open(url.toString(), '_blank')
  } catch {
    impersonateError.value = 'Impossible de générer le token.'
  } finally {
    impersonating.value = false
  }
}

async function confirmDelete() {
  if (!confirm(`Supprimer définitivement le compte de ${user.value?.email} ? Cette action est irréversible.`)) return
  await adminApi.deleteUser(id)
  router.push('/admin/users')
}

function statusClass(status: string | null) {
  return ({
    active: 'bg-emerald-900/50 text-emerald-400',
    canceled: 'bg-red-900/50 text-red-400',
    past_due: 'bg-amber-900/50 text-amber-400',
  } as Record<string, string>)[status ?? ''] ?? 'bg-gray-700 text-gray-400'
}

function statusLabel(status: string | null) {
  return ({
    active: 'Actif',
    canceled: 'Annulé',
    past_due: 'En retard',
    inactive: 'Inactif',
  } as Record<string, string>)[status ?? ''] ?? 'Aucun'
}

function formatDate(d: string) {
  return new Date(d).toLocaleDateString('fr-FR')
}

onMounted(load)
</script>
