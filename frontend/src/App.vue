<template>
  <div class="app-shell" :class="{ 'is-public': isPublicRoute }">
    <ToastContainer />
    <div v-if="sidebarOpen && !isPublicRoute" class="fixed inset-0 z-40 bg-black/40 lg:hidden" @click="sidebarOpen = false" />
    <aside v-if="!isPublicRoute" class="app-sidebar" :class="{ 'is-open': sidebarOpen }" @keydown.esc="sidebarOpen = false">
      <RouterLink :to="isAdminRoute ? '/admin/dashboard' : '/calendar'" class="brand"><span class="brand-mark">f.</span><span>frais<span class="font-normal">Zen</span><small>{{ isAdminRoute ? 'ADMINISTRATION' : 'VOTRE ESPACE PERSONNEL' }}</small></span></RouterLink>
      <button class="lg:hidden sidebar-close" aria-label="Fermer le menu" @click="sidebarOpen = false">×</button>
      <p class="nav-caption">{{ isAdminRoute ? 'PILOTAGE' : 'MON ESPACE' }}</p>
      <nav class="space-y-2" aria-label="Navigation principale">
        <RouterLink v-for="item in (isAdminRoute ? adminNav : nav)" :key="item.to" :to="item.to" class="nav-item" :class="{ selected: $route.path === item.to || $route.path.startsWith(item.to + '/') }"><AppIcon :name="item.icon" />{{ item.label }}</RouterLink>
      </nav>
      <section v-if="!isAdminRoute" class="profile-picker">
        <p class="nav-caption">PROFIL FISCAL</p>
        <p v-if="personStore.loading" class="text-sm text-gray-500">Chargement…</p>
        <button v-for="p in personStore.persons" :key="p.id" class="profile-option" :class="{ active: personStore.activePerson?.id === p.id }" @click="personStore.setActive(p)"><span class="avatar">{{ p.fullName.charAt(0) }}</span><span class="truncate">{{ p.fullName }}</span><span v-if="personStore.activePerson?.id === p.id" class="ml-auto">✓</span></button>
        <RouterLink to="/persons" class="text-xs text-indigo-600 block mt-3">+ {{ personStore.persons.length ? 'Gérer les profils' : 'Créer mon premier profil' }}</RouterLink>
      </section>
      <div class="sidebar-bottom">
        <div v-if="!isAdminRoute && authStore.user?.subscriptionStatus !== 'active'" class="membership-card"><span class="text-xs uppercase tracking-widest">FraisZen</span><p class="text-lg font-semibold mt-2 mb-3">Tous vos frais.<br />L’esprit tranquille.</p><RouterLink to="/pricing" class="block rounded-full bg-white/15 px-3 py-2 text-sm">Découvrir les offres ↗</RouterLink></div>
        <RouterLink v-if="isAdminRoute" to="/calendar" class="nav-item"><AppIcon name="arrow" />Retour à mon espace</RouterLink>
        <RouterLink v-else-if="authStore.user?.roles.includes('ROLE_ADMIN')" to="/admin/dashboard" class="nav-item"><AppIcon name="settings" />Administration</RouterLink>
        <button @click="logout" class="nav-item w-full"><AppIcon name="logout" />Déconnexion</button>
      </div>
    </aside>
    <main class="app-main" :class="{ 'is-public': isPublicRoute }">
      <header v-if="!isPublicRoute" class="app-header">
        <button @click="sidebarOpen = !sidebarOpen" class="icon-button lg:hidden" aria-label="Ouvrir le menu" :aria-expanded="sidebarOpen"><AppIcon name="menu" /></button>
        <span class="text-sm text-gray-500">{{ isAdminRoute ? 'Espace administration' : 'Mes frais professionnels' }}</span>
        <div class="ml-auto flex items-center gap-4"><ThemeToggle /><div v-if="authStore.user" class="header-user"><span class="avatar">{{ authStore.user.email.charAt(0).toUpperCase() }}</span><span class="hidden md:block text-xs text-gray-600 max-w-48 truncate">{{ authStore.user.email }}</span></div></div>
      </header>
      <RouterView />
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePersonStore } from '@/stores/personStore'
import { useAuthStore } from '@/stores/authStore'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import ToastContainer from '@/components/ui/ToastContainer.vue'

const personStore = usePersonStore()
const authStore = useAuthStore()
const router = useRouter()
const route = useRoute()

const sidebarOpen = ref(false)
const isPublicRoute = computed(() => !!route.meta.public)
const isAdminRoute = computed(() => route.path.startsWith('/admin'))

// Close sidebar on navigation
watch(route, () => { sidebarOpen.value = false })

function logout() {
  authStore.logout()
  router.push('/login')
}

const nav = [
  { to: '/calendar', label: 'Calendrier', icon: 'calendar' },
  { to: '/summary', label: 'Récapitulatif', icon: 'chart' },
  { to: '/receipts', label: 'Justificatifs', icon: 'receipt' },
  { to: '/trips', label: 'Gestion des trajets', icon: 'car' },
  { to: '/persons', label: 'Personnes', icon: 'users' },
  { to: '/settings', label: 'Paramètres', icon: 'settings' },
]
const adminNav = [
  { to: '/admin/dashboard', label: 'Tableau de bord', icon: 'chart' },
  { to: '/admin/users', label: 'Utilisateurs', icon: 'users' },
  { to: '/admin/fiscal-config', label: 'Config fiscale', icon: 'settings' },
  { to: '/admin/bareme-kilometrique', label: 'Barèmes km', icon: 'car' },
]

onMounted(async () => {
  const urlParams = new URLSearchParams(window.location.search)
  const impToken = urlParams.get('impersonate_token')
  if (impToken) {
    sessionStorage.setItem('jwt_token', impToken)
    authStore.token = impToken
    window.history.replaceState({}, '', window.location.pathname)
  }

  if (authStore.isAuthenticated) {
    await authStore.fetchMe()
    await personStore.fetchAll()
  }
})

watch(() => authStore.isAuthenticated, async (isAuth) => {
  if (isAuth) {
    await personStore.fetchAll()
  }
})
</script>
