<template>
  <div class="p-6">
    <h2 class="text-xl font-bold text-gray-100 mb-6">Tableau de bord</h2>

    <div v-if="loading" class="text-gray-400 text-sm">Chargement…</div>

    <div v-else class="grid grid-cols-2 gap-4 mb-8">
      <div class="bg-gray-800 rounded-xl p-5 border border-gray-700">
        <p class="text-xs text-gray-400 font-medium uppercase tracking-wider">Total utilisateurs</p>
        <p class="text-3xl font-bold text-white mt-2">{{ stats.totalUsers }}</p>
      </div>
      <div class="bg-gray-800 rounded-xl p-5 border border-gray-700">
        <p class="text-xs text-gray-400 font-medium uppercase tracking-wider">Abonnés actifs</p>
        <p class="text-3xl font-bold text-emerald-400 mt-2">{{ stats.activeUsers }}</p>
        <p class="text-xs text-gray-500 mt-1">{{ stats.inactiveUsers }} inactifs</p>
      </div>
      <div class="bg-gray-800 rounded-xl p-5 border border-gray-700">
        <p class="text-xs text-gray-400 font-medium uppercase tracking-wider">MRR</p>
        <p class="text-3xl font-bold text-indigo-400 mt-2">{{ fmt(stats.mrr) }}</p>
        <p class="text-xs text-gray-500 mt-1">Revenus mensuels récurrents</p>
      </div>
      <div class="bg-gray-800 rounded-xl p-5 border border-gray-700">
        <p class="text-xs text-gray-400 font-medium uppercase tracking-wider">ARR</p>
        <p class="text-3xl font-bold text-indigo-300 mt-2">{{ fmt(stats.arr) }}</p>
        <p class="text-xs text-gray-500 mt-1">Revenus annuels récurrents</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { adminApi, type AdminStats } from '@/api/adminApi'

const loading = ref(true)
const stats = ref<AdminStats>({ totalUsers: 0, activeUsers: 0, inactiveUsers: 0, mrr: 0, arr: 0 })

function fmt(v: number) {
  return v.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' })
}

onMounted(async () => {
  try {
    stats.value = await adminApi.getStats()
  } finally {
    loading.value = false
  }
})
</script>
