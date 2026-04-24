<template>
  <div class="min-h-screen bg-gray-50 flex">
    <aside v-if="!isPublicRoute" class="w-64 bg-white border-r border-gray-200 flex flex-col shrink-0">
      <div class="px-6 py-5 border-b border-gray-200">
        <h1 class="text-lg font-bold text-gray-900">Frais Réels</h1>
        <p class="text-xs text-gray-500 mt-0.5">Déclaration d'impôts</p>
      </div>

      <div class="px-4 py-4 border-b border-gray-200">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Personne</p>
        <div v-if="personStore.loading" class="text-sm text-gray-400">Chargement…</div>
        <div v-else-if="personStore.persons.length === 0" class="text-sm text-gray-400">Aucune personne</div>
        <div v-else class="space-y-1">
          <button
            v-for="p in personStore.persons" :key="p.id"
            @click="personStore.setActive(p)"
            :class="['w-full text-left px-3 py-2 rounded-lg text-sm transition-colors',
              personStore.activePerson?.id === p.id ? 'bg-indigo-50 text-indigo-700 font-medium' : 'text-gray-700 hover:bg-gray-50']"
          >{{ p.fullName }}</button>
        </div>
        <RouterLink to="/persons" class="mt-2 block px-3 py-1.5 text-xs text-gray-400 hover:text-gray-600 hover:bg-gray-50 rounded-lg transition-colors">
          + Gérer les personnes
        </RouterLink>
      </div>

      <nav class="flex-1 px-4 py-4 space-y-1">
        <RouterLink
          v-for="item in nav" :key="item.to" :to="item.to"
          :class="['flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition-colors',
            $route.path === item.to ? 'bg-indigo-50 text-indigo-700' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900']"
        >
          <span class="text-lg leading-none">{{ item.icon }}</span>{{ item.label }}
        </RouterLink>
      </nav>

      <div class="px-4 py-4 border-t border-gray-200 space-y-1.5">
        <div v-for="t in types" :key="t.label" class="flex items-center gap-2 text-xs text-gray-600">
          <span :class="['w-3 h-3 rounded-full shrink-0', t.color]"></span>{{ t.label }}
        </div>
      </div>

      <div class="px-4 pb-4 border-t border-gray-200 pt-3">
        <p v-if="authStore.user" class="text-xs text-gray-400 truncate mb-2">{{ authStore.user.email }}</p>
        <button @click="logout" class="w-full text-left px-3 py-1.5 text-xs text-gray-500 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors">
          Déconnexion
        </button>
      </div>
    </aside>

    <main class="flex-1 overflow-auto">
      <RouterView />
    </main>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePersonStore } from '@/stores/personStore'
import { useAuthStore } from '@/stores/authStore'

const personStore = usePersonStore()
const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()
const isPublicRoute = computed(() => !!route.meta.public)

function logout() {
  authStore.logout()
  router.push('/login')
}
const nav = [
  { to: '/calendar', label: 'Calendrier', icon: '📅' },
  { to: '/summary', label: 'Récapitulatif', icon: '📊' },
  { to: '/persons', label: 'Personnes', icon: '👤' },
]
const types = [
  { label: 'Trajet', color: 'bg-blue-500' },
  { label: 'Télétravail', color: 'bg-emerald-500' },
  { label: 'Péage', color: 'bg-amber-500' },
]
onMounted(() => {
  if (authStore.isAuthenticated) {
    authStore.fetchMe()
    personStore.fetchAll()
  }
})
</script>
