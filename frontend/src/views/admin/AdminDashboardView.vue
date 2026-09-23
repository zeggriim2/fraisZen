<template>
  <div class="workspace-page admin-workspace">
    <AdminSectionNav />
    <div class="workspace-title"><div><span class="eyebrow">ACTIVITÉ DE LA PLATEFORME</span><h2>Tout commence par une vue claire.</h2><p>Une vue d’ensemble de votre activité FraisZen.</p></div><RouterLink to="/admin/users" class="bg-indigo-600 text-white rounded-full px-5 py-3 text-sm">Gérer les utilisateurs ↗</RouterLink></div>
    <p v-if="loading" role="status" class="text-gray-500 py-12">Chargement de votre activité…</p>
    <div v-else-if="error" role="alert" class="metric-card"><p>{{ error }}</p><button @click="load" class="mt-4 text-indigo-600">Réessayer</button></div>
    <template v-else>
      <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <article class="metric-card featured"><span class="text-sm">Utilisateurs</span><strong>{{ stats.totalUsers }}</strong><p>Comptes enregistrés</p></article>
        <article class="metric-card"><span class="text-sm">Abonnements actifs</span><strong>{{ stats.activeUsers }}</strong><p>{{ stats.inactiveUsers }} comptes inactifs</p></article>
        <article class="metric-card"><span class="text-sm">Revenu mensuel</span><strong>{{ fmt(stats.mrr) }}</strong><p>Revenus récurrents · MRR</p></article>
        <article class="metric-card"><span class="text-sm">Revenu annuel</span><strong>{{ fmt(stats.arr) }}</strong><p>Revenus récurrents · ARR</p></article>
      </div>
      <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <section class="metric-card"><h3 class="font-semibold">Répartition des abonnements</h3><div class="flex flex-wrap items-center gap-8 py-8"><div class="subscription-ring" :style="{ '--progress': `${activePercent}%` }" role="img" :aria-label="`${activePercent} % des comptes sont actifs`"><span>{{ activePercent }}<small>% actifs</small></span></div><div class="space-y-4"><p><span class="inline-block w-2 h-2 rounded-full bg-indigo-500 mr-2" />{{ stats.activeUsers }} actifs</p><p><span class="inline-block w-2 h-2 rounded-full bg-gray-200 mr-2" />{{ stats.inactiveUsers }} inactifs</p><p v-if="!stats.totalUsers">Aucun compte pour le moment.</p></div></div><RouterLink to="/admin/users" class="text-sm text-indigo-600">Consulter les utilisateurs →</RouterLink></section>
        <section class="metric-card"><h3 class="font-semibold mb-6">Gestion de la plateforme</h3><RouterLink v-for="item in shortcuts" :key="item.to" :to="item.to" class="flex items-center gap-4 py-5 border-b border-gray-100 last:border-0"><span class="avatar"><AppIcon :name="item.icon" /></span><div class="flex-1"><h4 class="text-sm font-medium">{{ item.title }}</h4><p class="mt-1">{{ item.description }}</p></div><span aria-hidden="true">↗</span></RouterLink></section>
      </div>
    </template>
  </div>
</template>
<script setup lang="ts">
import AdminSectionNav from '@/components/ui/AdminSectionNav.vue'
import { ref, computed, onMounted } from 'vue'
import { adminApi, type AdminStats } from '@/api/adminApi'
import AppIcon from '@/components/ui/AppIcon.vue'
const loading = ref(true)
const error = ref('')
const stats = ref<AdminStats>({ totalUsers: 0, activeUsers: 0, inactiveUsers: 0, mrr: 0, arr: 0 })
const activePercent = computed(() => stats.value.totalUsers ? Math.round(stats.value.activeUsers / stats.value.totalUsers * 100) : 0)
const shortcuts = [
  { to: '/admin/users', icon: 'users', title: 'Utilisateurs', description: 'Comptes, accès et abonnements' },
  { to: '/admin/fiscal-config', icon: 'settings', title: 'Configuration fiscale', description: 'Paramètres de calcul par année' },
  { to: '/admin/bareme-kilometrique', icon: 'car', title: 'Barèmes kilométriques', description: 'Voitures, motos et motorisations électriques' },
]
function fmt(v: number) { return v.toLocaleString('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }) }
async function load() {
  loading.value = true; error.value = ''
  try { stats.value = await adminApi.getStats() }
  catch { error.value = 'Impossible de charger les statistiques. Veuillez réessayer.' }
  finally { loading.value = false }
}
onMounted(load)
</script>
<style scoped>
.subscription-ring { width: 172px; height: 172px; flex-shrink: 0; padding: 16px; border-radius: 50%; background: conic-gradient(rgb(var(--accent-500)) var(--progress), rgb(var(--gray-100)) 0); }
.subscription-ring > span { display: flex; align-items: center; justify-content: center; flex-direction: column; height: 100%; border-radius: 50%; background: rgb(var(--surface)); font-size: 38px; letter-spacing: -1px; }
.subscription-ring small { font-size: 12px; letter-spacing: 0; color: rgb(var(--gray-500)); }
</style>
