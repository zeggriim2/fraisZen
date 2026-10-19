<template>
  <AuthCard title="Nouveau mot de passe" subtitle="Choisissez un nouveau mot de passe pour votre compte.">
    <AlertBanner v-if="!token" variant="danger">
      Lien invalide. Veuillez faire une nouvelle
      <RouterLink to="/forgot-password" class="font-semibold underline underline-offset-2">demande de réinitialisation</RouterLink>.
    </AlertBanner>

    <form v-else-if="!done" @submit.prevent="submit" class="space-y-5">
      <FormField label="Nouveau mot de passe" field-id="reset-password" hint="8 caractères minimum." v-slot="{ id, describedBy }">
        <input :id="id" v-model="newPassword" type="password" required autocomplete="new-password" minlength="8" class="form-control" :aria-describedby="describedBy" />
      </FormField>
      <FormField label="Confirmer le mot de passe" field-id="reset-confirm" :error="mismatch ? 'Les mots de passe ne correspondent pas.' : undefined" v-slot="{ id, describedBy, invalid }">
        <input :id="id" v-model="confirm" type="password" required autocomplete="new-password" class="form-control" :aria-describedby="describedBy" :aria-invalid="invalid || undefined" @blur="confirmTouched = true" />
      </FormField>

      <AlertBanner v-if="error" variant="danger">{{ error }}</AlertBanner>

      <BaseButton type="submit" block size="lg" :loading="loading">
        {{ loading ? 'Enregistrement…' : 'Enregistrer le mot de passe' }}
      </BaseButton>
    </form>

    <div v-else class="text-center space-y-4" role="status">
      <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-success-soft text-success-ink"><AppIcon name="check-circle" :size="24" /></span>
      <p class="text-sm text-ink">Mot de passe modifié avec succès.</p>
      <BaseButton to="/login" block size="lg">Se connecter</BaseButton>
    </div>
  </AuthCard>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import { authApi } from '@/api/authApi'
import AuthCard from '@/components/auth/AuthCard.vue'
import FormField from '@/components/ui/FormField.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AlertBanner from '@/components/ui/AlertBanner.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const route = useRoute()
const token = ref((route.query.token as string) ?? '')
const newPassword = ref('')
const confirm = ref('')
const confirmTouched = ref(false)
const loading = ref(false)
const error = ref('')
const done = ref(false)

const mismatch = computed(() => confirmTouched.value && confirm.value.length > 0 && newPassword.value !== confirm.value)

async function submit() {
  error.value = ''
  confirmTouched.value = true
  if (newPassword.value !== confirm.value) {
    error.value = 'Les mots de passe ne correspondent pas.'
    return
  }
  loading.value = true
  try {
    await authApi.resetPassword(token.value, newPassword.value)
    done.value = true
  } catch {
    error.value = 'Le lien de réinitialisation est invalide ou a expiré.'
  } finally {
    loading.value = false
  }
}
</script>
