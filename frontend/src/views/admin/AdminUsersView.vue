<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-bold text-gray-100">Utilisateurs</h2>
      <button @click="exportCsv" class="px-4 py-2 bg-gray-700 text-gray-200 text-sm rounded-lg hover:bg-gray-600 transition-colors">
        Exporter CSV
      </button>
    </div>

    <!-- Filters -->
    <div class="flex gap-3 mb-5">
      <input
        v-model="search"
        @input="debouncedLoad"
        type="text"
        placeholder="Rechercher par email…"
        class="flex-1 px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-200 placeholder-gray-500 focus:outline-none focus:border-indigo-500"
      />
      <select
        v-model="statusFilter"
        @change="load"
        class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-200 focus:outline-none focus:border-indigo-500"
      >
        <option value="">Tous les statuts</option>
        <option value="active">Actif</option>
        <option value="canceled">Annulé</option>
        <option value="inactive">Inactif</option>
        <option value="past_due">En retard</option>
      </select>
    </div>

    <!-- Table -->
    <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden">
      <div v-if="loading" class="p-8 text-center text-gray-400 text-sm">Chargement…</div>
      <table v-else class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-700">
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Email</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Statut</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Inscription</th>
            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Personnes</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users" :key="user.id" class="border-b border-gray-700/50 hover:bg-gray-700/30 transition-colors">
            <td class="px-4 py-3 text-gray-200">{{ user.email }}</td>
            <td class="px-4 py-3">
              <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statusClass(user.subscriptionStatus)]">
                {{ statusLabel(user.subscriptionStatus) }}
              </span>
            </td>
            <td class="px-4 py-3 text-gray-400">{{ formatDate(user.createdAt) }}</td>
            <td class="px-4 py-3 text-gray-400">{{ user.personCount }}</td>
            <td class="px-4 py-3 text-right">
              <RouterLink :to="`/admin/users/${user.id}`" class="text-indigo-400 hover:text-indigo-300 text-xs font-medium">
                Voir →
              </RouterLink>
            </td>
          </tr>
          <tr v-if="users.length === 0">
            <td colspan="5" class="px-4 py-8 text-center text-gray-500 text-sm">Aucun utilisateur trouvé</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="pages > 1" class="flex justify-center gap-2 mt-4">
      <button
        v-for="p in pages" :key="p"
        @click="page = p; load()"
        :class="['w-8 h-8 rounded-lg text-sm font-medium transition-colors',
          p === page ? 'bg-indigo-600 text-white' : 'bg-gray-800 text-gray-400 hover:bg-gray-700']"
      >{{ p }}</button>
    </div>

    <p class="text-xs text-gray-500 text-right mt-2">{{ total }} utilisateur{{ total > 1 ? 's' : '' }} au total</p>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { adminApi, type AdminUser } from '@/api/adminApi'

const users = ref<AdminUser[]>([])
const loading = ref(true)
const search = ref('')
const statusFilter = ref('')
const page = ref(1)
const total = ref(0)
const pages = ref(1)

let debounceTimer: ReturnType<typeof setTimeout>

function debouncedLoad() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { page.value = 1; load() }, 300)
}

async function load() {
  loading.value = true
  try {
    const res = await adminApi.getUsers({
      search: search.value || undefined,
      status: statusFilter.value || undefined,
      page: page.value,
    })
    users.value = res.items
    total.value = res.total
    pages.value = res.pages
  } finally {
    loading.value = false
  }
}

async function exportCsv() {
  const blob = await adminApi.exportCsv()
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `users-${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
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
