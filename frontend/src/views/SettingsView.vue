<template>
  <div class="workspace-page settings-workspace max-w-3xl mx-auto space-y-6">
    <PageHeader eyebrow="Votre espace" title="Paramètres" description="Compte, sécurité, abonnement et préférences." />

    <!-- Compte -->
    <BaseCard title="Mon compte" title-tag="h2">
      <form @submit.prevent="saveProfile" class="space-y-5">
        <FormField label="Adresse e-mail" field-id="settings-email" v-slot="{ id, describedBy }">
          <input :id="id" v-model="form.email" type="email" required autocomplete="email" class="form-control" :aria-describedby="describedBy" />
        </FormField>
        <div class="flex flex-wrap items-center gap-3">
          <BaseButton type="submit" :loading="profileSaving">{{ profileSaving ? 'Enregistrement…' : 'Enregistrer' }}</BaseButton>
          <span v-if="profileSuccess" class="inline-flex items-center gap-1.5 text-sm text-success-ink" role="status"><AppIcon name="check-circle" :size="16" />Modifications enregistrées.</span>
          <span v-if="profileError" class="text-sm text-danger-ink" role="alert">{{ profileError }}</span>
        </div>
      </form>
    </BaseCard>

    <!-- Mot de passe -->
    <BaseCard title="Mot de passe" title-tag="h2">
      <form @submit.prevent="savePassword" class="space-y-5">
        <FormField label="Mot de passe actuel" field-id="settings-password-current" v-slot="{ id }">
          <input :id="id" v-model="passwordForm.current" type="password" required autocomplete="current-password" class="form-control" />
        </FormField>
        <FormField label="Nouveau mot de passe" field-id="settings-password-next" hint="8 caractères minimum." v-slot="{ id, describedBy }">
          <input :id="id" v-model="passwordForm.next" type="password" required minlength="8" autocomplete="new-password" class="form-control" :aria-describedby="describedBy" />
        </FormField>
        <div class="flex flex-wrap items-center gap-3">
          <BaseButton type="submit" :loading="passwordSaving">{{ passwordSaving ? 'Modification…' : 'Modifier le mot de passe' }}</BaseButton>
          <span v-if="passwordSuccess" class="inline-flex items-center gap-1.5 text-sm text-success-ink" role="status"><AppIcon name="check-circle" :size="16" />Mot de passe modifié.</span>
          <span v-if="passwordError" class="text-sm text-danger-ink" role="alert">{{ passwordError }}</span>
        </div>
      </form>
    </BaseCard>

    <!-- Abonnement -->
    <BaseCard title="Abonnement" title-tag="h2">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <p class="text-sm text-ink flex items-center gap-2">
            Statut :
            <span :class="['badge', statusBadge]">{{ statusLabel }}</span>
          </p>
          <p v-if="authStore.user?.subscriptionStatus === 'active'" class="text-xs text-ink-muted mt-1">
            Gérez votre carte, vos factures ou annulez depuis le portail Stripe.
          </p>
          <p v-else class="text-xs text-ink-muted mt-1">
            Abonnez-vous pour accéder à toutes les fonctionnalités.
          </p>
        </div>

        <BaseButton v-if="authStore.user?.subscriptionStatus !== 'active'" to="/pricing" class="shrink-0">Voir les offres</BaseButton>
        <BaseButton v-else variant="secondary" icon="credit-card" :loading="portalLoading" class="shrink-0" @click="openPortal">
          {{ portalLoading ? 'Redirection…' : 'Gérer mon abonnement' }}
        </BaseButton>
      </div>
    </BaseCard>

    <!-- Préférences -->
    <BaseCard title="Préférences" title-tag="h2">
      <form @submit.prevent="saveProfile" class="space-y-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <FormField label="Année fiscale par défaut" field-id="settings-default-year" :hint="defaultYearHint" v-slot="{ id, describedBy }">
            <select :id="id" v-model="form.defaultYear" class="form-control" :aria-describedby="describedBy">
              <option :value="null">Aucune (automatique)</option>
              <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
          </FormField>
          <FormField label="Puissance fiscale par défaut" field-id="settings-default-cv" v-slot="{ id }">
            <select :id="id" v-model="form.defaultFiscalPower" class="form-control">
              <option :value="null">Aucune</option>
              <option v-for="p in fiscalPowers" :key="p" :value="p">{{ p }} CV</option>
            </select>
          </FormField>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <BaseButton type="submit" :loading="profileSaving">{{ profileSaving ? 'Enregistrement…' : 'Enregistrer' }}</BaseButton>
          <span v-if="profileSuccess" class="inline-flex items-center gap-1.5 text-sm text-success-ink" role="status"><AppIcon name="check-circle" :size="16" />Modifications enregistrées.</span>
        </div>
      </form>
    </BaseCard>

    <!-- Zone de danger -->
    <BaseCard title="Zone de danger" title-tag="h2" class="!border-danger/40">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <p class="text-sm text-ink font-semibold">Supprimer mon compte</p>
          <p class="text-xs text-ink-muted mt-1">Action irréversible. Toutes vos données seront supprimées.</p>
        </div>
        <BaseButton variant="danger" icon="trash" class="shrink-0" @click="confirmDelete">Supprimer</BaseButton>
      </div>
    </BaseCard>

    <ConfirmModal
      v-if="showDeleteConfirm"
      title="Supprimer définitivement votre compte ?"
      message="Cette action est irréversible et supprimera toutes vos données : profils, frais, justificatifs."
      confirm-label="Supprimer mon compte"
      cancel-label="Annuler"
      variant="danger"
      :icon="true"
      @confirm="doDelete"
      @cancel="showDeleteConfirm = false"
    />
  </div>
</template>

<script setup lang="ts">
import { reactive, ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'
import { billingApi } from '@/api/billingApi'
import PageHeader from '@/components/ui/PageHeader.vue'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import FormField from '@/components/ui/FormField.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import ConfirmModal from '@/components/ui/ConfirmModal.vue'

const authStore = useAuthStore()
const router = useRouter()

const _nowDate = new Date()
const currentYear = _nowDate.getFullYear()
const isDeclarationSeason = _nowDate.getMonth() < 6
const years = Array.from({ length: 8 }, (_, i) => currentYear - 5 + i)
// Texte d'aide contextuel : pointe vers l'année à déclarer selon la période
const defaultYearHint = isDeclarationSeason
  ? `En période de déclaration, "Automatique" affiche ${currentYear - 1} par défaut.`
  : `Hors période de déclaration, "Automatique" affiche ${currentYear} par défaut.`
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

const statusBadge = computed(() => {
  switch (authStore.user?.subscriptionStatus) {
    case 'active': return 'badge-success'
    case 'past_due': return 'badge-warning'
    default: return 'badge-neutral'
  }
})

// Suppression
const showDeleteConfirm = ref(false)
function confirmDelete() { showDeleteConfirm.value = true }
async function doDelete() {
  showDeleteConfirm.value = false
  await authStore.deleteAccount()
  router.push('/login')
}
</script>
