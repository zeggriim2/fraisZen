<template>
  <div class="p-8 max-w-2xl mx-auto space-y-8">
    <h1 class="text-2xl font-bold text-gray-900">Paramètres</h1>

    <!-- Compte -->
    <section class="bg-white rounded-2xl border border-gray-200 divide-y divide-gray-100">
      <div class="px-6 py-4">
        <h2 class="text-sm font-semibold text-gray-700">Mon compte</h2>
      </div>

      <form @submit.prevent="saveProfile" class="px-6 py-5 space-y-4">
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Adresse e-mail</label>
          <input v-model="form.email" type="email" required
            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300" />
        </div>
        <div class="flex items-center gap-3">
          <button type="submit" :disabled="profileSaving"
            class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors">
            {{ profileSaving ? 'Enregistrement…' : 'Enregistrer' }}
          </button>
          <span v-if="profileSuccess" class="text-sm text-emerald-600">Modifications enregistrées.</span>
          <span v-if="profileError" class="text-sm text-red-600">{{ profileError }}</span>
        </div>
      </form>
    </section>

    <!-- Mot de passe -->
    <section class="bg-white rounded-2xl border border-gray-200 divide-y divide-gray-100">
      <div class="px-6 py-4">
        <h2 class="text-sm font-semibold text-gray-700">Mot de passe</h2>
      </div>

      <form @submit.prevent="savePassword" class="px-6 py-5 space-y-4">
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Mot de passe actuel</label>
          <input v-model="passwordForm.current" type="password" required
            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Nouveau mot de passe</label>
          <input v-model="passwordForm.next" type="password" required minlength="8"
            class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300" />
        </div>
        <div class="flex items-center gap-3">
          <button type="submit" :disabled="passwordSaving"
            class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors">
            {{ passwordSaving ? 'Modification…' : 'Modifier le mot de passe' }}
          </button>
          <span v-if="passwordSuccess" class="text-sm text-emerald-600">Mot de passe modifié.</span>
          <span v-if="passwordError" class="text-sm text-red-600">{{ passwordError }}</span>
        </div>
      </form>
    </section>

    <!-- Abonnement -->
    <section class="bg-white rounded-2xl border border-gray-200 divide-y divide-gray-100">
      <div class="px-6 py-4">
        <h2 class="text-sm font-semibold text-gray-700">Abonnement</h2>
      </div>

      <div class="px-6 py-5 flex items-center justify-between gap-4">
        <div>
          <p class="text-sm text-gray-700">
            Statut :
            <span :class="statusClass">{{ statusLabel }}</span>
          </p>
          <p v-if="authStore.user?.subscriptionStatus === 'active'" class="text-xs text-gray-400 mt-0.5">
            Gérez votre carte, vos factures ou annulez depuis le portail Stripe.
          </p>
          <p v-else class="text-xs text-gray-400 mt-0.5">
            Abonnez-vous pour accéder à toutes les fonctionnalités.
          </p>
        </div>

        <RouterLink v-if="authStore.user?.subscriptionStatus !== 'active'" to="/pricing"
          class="shrink-0 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
          Voir les offres
        </RouterLink>
        <button v-else @click="openPortal" :disabled="portalLoading"
          class="shrink-0 px-4 py-2 border border-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 disabled:opacity-50 transition-colors">
          {{ portalLoading ? 'Redirection…' : 'Gérer mon abonnement' }}
        </button>
      </div>
    </section>

    <!-- Préférences -->
    <section class="bg-white rounded-2xl border border-gray-200 divide-y divide-gray-100">
      <div class="px-6 py-4">
        <h2 class="text-sm font-semibold text-gray-700">Préférences</h2>
      </div>

      <form @submit.prevent="saveProfile" class="px-6 py-5 space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Année fiscale par défaut</label>
            <select v-model="form.defaultYear"
              class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
              <option :value="null">Aucune (année en cours)</option>
              <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Puissance fiscale par défaut</label>
            <select v-model="form.defaultFiscalPower"
              class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300">
              <option :value="null">Aucune</option>
              <option v-for="p in fiscalPowers" :key="p" :value="p">{{ p }} CV</option>
            </select>
          </div>
        </div>
        <div class="flex items-center gap-3">
          <button type="submit" :disabled="profileSaving"
            class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors">
            {{ profileSaving ? 'Enregistrement…' : 'Enregistrer' }}
          </button>
          <span v-if="profileSuccess" class="text-sm text-emerald-600">Modifications enregistrées.</span>
        </div>
      </form>
    </section>

    <!-- Zone de danger -->
    <section class="bg-white rounded-2xl border border-red-200 divide-y divide-red-100">
      <div class="px-6 py-4">
        <h2 class="text-sm font-semibold text-red-600">Zone de danger</h2>
      </div>

      <div class="px-6 py-5 flex items-center justify-between gap-4">
        <div>
          <p class="text-sm text-gray-700 font-medium">Supprimer mon compte</p>
          <p class="text-xs text-gray-400 mt-0.5">Action irréversible. Toutes vos données seront supprimées.</p>
        </div>
        <button @click="confirmDelete"
          class="shrink-0 px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors">
          Supprimer
        </button>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'
import { billingApi } from '@/api/billingApi'

const authStore = useAuthStore()
const router = useRouter()

const currentYear = new Date().getFullYear()
const years = Array.from({ length: 8 }, (_, i) => currentYear - 5 + i)
const fiscalPowers = [3, 4, 5, 6, 7]

const form = reactive({
  email: authStore.user?.email ?? '',
  defaultYear: authStore.user?.defaultYear ?? null,
  defaultFiscalPower: authStore.user?.defaultFiscalPower ?? null,
})

onMounted(() => {
  if (authStore.user) {
    form.email = authStore.user.email
    form.defaultYear = authStore.user.defaultYear
    form.defaultFiscalPower = authStore.user.defaultFiscalPower
  }
})

// Profil
const profileSaving = ref(false)
const profileSuccess = ref(false)
const profileError = ref('')

async function saveProfile() {
  profileSaving.value = true
  profileSuccess.value = false
  profileError.value = ''
  try {
    await authStore.updateProfile({
      email: form.email,
      defaultYear: form.defaultYear,
      defaultFiscalPower: form.defaultFiscalPower,
    })
    profileSuccess.value = true
    setTimeout(() => { profileSuccess.value = false }, 3000)
  } catch (e: any) {
    profileError.value = e.response?.data?.error ?? 'Une erreur est survenue.'
  } finally {
    profileSaving.value = false
  }
}

// Mot de passe
const passwordForm = reactive({ current: '', next: '' })
const passwordSaving = ref(false)
const passwordSuccess = ref(false)
const passwordError = ref('')

async function savePassword() {
  passwordSaving.value = true
  passwordSuccess.value = false
  passwordError.value = ''
  try {
    await authStore.updatePassword(passwordForm.current, passwordForm.next)
    passwordSuccess.value = true
    passwordForm.current = ''
    passwordForm.next = ''
    setTimeout(() => { passwordSuccess.value = false }, 3000)
  } catch (e: any) {
    passwordError.value = e.response?.data?.error ?? 'Une erreur est survenue.'
  } finally {
    passwordSaving.value = false
  }
}

// Portail Stripe
const portalLoading = ref(false)

async function openPortal() {
  portalLoading.value = true
  try {
    const { url } = await billingApi.openPortal()
    window.location.href = url
  } finally {
    portalLoading.value = false
  }
}

// Statut abonnement
const statusLabel = computed(() => {
  switch (authStore.user?.subscriptionStatus) {
    case 'active': return 'Actif'
    case 'past_due': return 'Paiement en attente'
    case 'canceled': return 'Annulé'
    default: return 'Inactif'
  }
})

const statusClass = computed(() => {
  switch (authStore.user?.subscriptionStatus) {
    case 'active': return 'font-semibold text-emerald-600'
    case 'past_due': return 'font-semibold text-amber-600'
    default: return 'font-semibold text-gray-400'
  }
})

// Suppression
async function confirmDelete() {
  if (!confirm('Êtes-vous sûr ? Cette action est irréversible et supprimera toutes vos données.')) return
  await authStore.deleteAccount()
  router.push('/login')
}
</script>
