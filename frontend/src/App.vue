<template>
  <div class="app-shell" :class="{ 'is-public': isPublicRoute }">
    <a href="#main-content" class="skip-link">Aller au contenu principal</a>
    <ToastContainer />

    <Transition enter-active-class="transition-opacity duration-200 motion-reduce:transition-none" enter-from-class="opacity-0" leave-active-class="transition-opacity duration-150 motion-reduce:transition-none" leave-to-class="opacity-0">
      <div v-if="sidebarOpen && !isPublicRoute" class="fixed inset-0 z-40 bg-gray-950/50 lg:hidden" aria-hidden="true" @click="sidebarOpen = false" />
    </Transition>

    <aside
      v-if="!isPublicRoute"
      id="app-sidebar"
      class="app-sidebar"
      :class="{ 'is-open': sidebarOpen }"
      :aria-hidden="isDesktop ? undefined : !sidebarOpen"
      @keydown.esc="sidebarOpen = false"
    >
      <RouterLink :to="isAdminRoute ? '/admin/dashboard' : '/calendar'" class="brand">
        <span class="brand-mark" aria-hidden="true">f.</span>
        <span>frais<span class="font-normal">Zen</span><small>{{ isAdminRoute ? 'Administration' : 'Frais Réels · Mon espace' }}</small></span>
      </RouterLink>
      <button type="button" class="icon-button is-plain sidebar-close lg:hidden" aria-label="Fermer le menu" @click="sidebarOpen = false"><AppIcon name="x" /></button>

      <p class="nav-caption">{{ isAdminRoute ? 'Pilotage' : 'Navigation' }}</p>
      <nav class="flex flex-col gap-1" aria-label="Navigation principale">
        <RouterLink
          v-for="item in (isAdminRoute ? adminNav : nav)"
          :key="item.to"
          :to="item.to"
          class="nav-item"
          :class="{ selected: isActive(item.to) }"
          :aria-current="isActive(item.to) ? 'page' : undefined"
        >
          <AppIcon :name="item.icon" />{{ item.label }}
        </RouterLink>
      </nav>

      <section v-if="!isAdminRoute" class="profile-picker" aria-labelledby="profile-picker-title">
        <p id="profile-picker-title" class="nav-caption">Profil fiscal</p>
        <p v-if="personStore.loading" class="px-2 py-2 text-sm text-ink-subtle" role="status">Chargement…</p>
        <div v-else class="flex flex-col gap-1" role="radiogroup" aria-labelledby="profile-picker-title">
          <button
            v-for="p in personStore.persons"
            :key="p.id"
            type="button"
            role="radio"
            :aria-checked="personStore.activePerson?.id === p.id"
            class="profile-option"
            :class="{ active: personStore.activePerson?.id === p.id }"
            @click="personStore.setActive(p)"
          >
            <span class="avatar" aria-hidden="true">{{ p.fullName.charAt(0) }}</span>
            <span class="truncate">{{ p.fullName }}</span>
            <AppIcon v-if="personStore.activePerson?.id === p.id" name="check" :size="16" class="ml-auto text-brand-600" />
          </button>
        </div>
        <RouterLink to="/persons" class="btn-link mt-1 px-2 text-xs">
          <AppIcon name="plus" :size="14" />{{ personStore.persons.length ? 'Gérer les profils' : 'Créer mon premier profil' }}
        </RouterLink>
      </section>

      <div class="sidebar-bottom">
        <div v-if="!isAdminRoute && authStore.user?.subscriptionStatus !== 'active'" class="membership-card">
          <span class="eyebrow !text-current opacity-80">FraisZen</span>
          <p class="font-display text-base font-semibold mt-2 mb-3">Tous vos frais.<br />L’esprit tranquille.</p>
          <RouterLink to="/pricing" class="btn btn-sm w-full bg-white/15 text-current hover:bg-white/25 border-white/20">Découvrir les offres<AppIcon name="arrow-up-right" :size="16" /></RouterLink>
        </div>
        <RouterLink v-if="isAdminRoute" to="/calendar" class="nav-item"><AppIcon name="arrow-left" />Retour à mon espace</RouterLink>
        <RouterLink v-else-if="authStore.user?.roles.includes('ROLE_ADMIN')" to="/admin/dashboard" class="nav-item"><AppIcon name="shield" />Administration</RouterLink>
        <button type="button" class="nav-item w-full text-left" @click="logout"><AppIcon name="logout" />Déconnexion</button>
      </div>
    </aside>

    <main id="main-content" class="app-main" :class="{ 'is-public': isPublicRoute }" tabindex="-1">
      <header v-if="!isPublicRoute" class="app-header">
        <button type="button" class="icon-button lg:hidden" aria-label="Ouvrir le menu" aria-controls="app-sidebar" :aria-expanded="sidebarOpen" @click="sidebarOpen = !sidebarOpen"><AppIcon name="menu" /></button>
        <span class="text-sm font-medium text-ink-muted truncate">{{ isAdminRoute ? 'Espace administration' : 'Mes frais professionnels' }}</span>
        <div class="ml-auto flex items-center gap-3">
          <ThemeToggle />
          <div v-if="authStore.user" class="header-user">
            <span class="avatar" aria-hidden="true">{{ authStore.user.email.charAt(0).toUpperCase() }}</span>
            <span class="hidden md:block text-sm text-ink-muted max-w-48 truncate">{{ authStore.user.email }}</span>
          </div>
        </div>
      </header>
      <RouterView />
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
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

const desktopQuery = window.matchMedia('(min-width: 1024px)')
const isDesktop = ref(desktopQuery.matches)
function onDesktopChange(e: MediaQueryListEvent) { isDesktop.value = e.matches; if (e.matches) sidebarOpen.value = false }
onMounted(() => desktopQuery.addEventListener('change', onDesktopChange))
onUnmounted(() => desktopQuery.removeEventListener('change', onDesktopChange))

function isActive(to: string) { return route.path === to || route.path.startsWith(to + '/') }

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
