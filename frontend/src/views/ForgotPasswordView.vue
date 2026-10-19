<template>
  <AuthCard title="Mot de passe oublié" subtitle="Saisissez votre email pour recevoir un lien de réinitialisation.">
    <form v-if="!sent" @submit.prevent="submit" class="space-y-5">
      <FormField label="Email" field-id="forgot-email" v-slot="{ id, describedBy }">
        <input :id="id" v-model="email" type="email" required autocomplete="email" inputmode="email" class="form-control"
          placeholder="vous@exemple.fr" :aria-describedby="describedBy" />
      </FormField>

      <AlertBanner v-if="error" variant="danger">{{ error }}</AlertBanner>

      <BaseButton type="submit" block size="lg" :loading="loading">
        {{ loading ? 'Envoi…' : 'Envoyer le lien' }}
      </BaseButton>
    </form>

    <div v-else class="text-center space-y-3" role="status">
      <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-success-soft text-success-ink"><AppIcon name="check-circle" :size="24" /></span>
      <p class="text-sm text-ink-muted">
        Si un compte existe pour <strong class="text-ink">{{ email }}</strong>, vous recevrez un email avec un lien valable 24 heures.
      </p>
    </div>

    <template #footer>
      <p><RouterLink to="/login" class="btn-link"><AppIcon name="arrow-left" :size="16" />Retour à la connexion</RouterLink></p>
    </template>
  </AuthCard>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { authApi } from '@/api/authApi'
import AuthCard from '@/components/auth/AuthCard.vue'
import FormField from '@/components/ui/FormField.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AlertBanner from '@/components/ui/AlertBanner.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const email = ref('')
const loading = ref(false)
const error = ref('')
const sent = ref(false)

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await authApi.forgotPassword(email.value)
    sent.value = true
  } catch {
    error.value = 'Une erreur est survenue. Veuillez réessayer.'
  } finally {
    loading.value = false
  }
}
</script>
