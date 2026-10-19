<template>
  <AuthCard title="Créez votre espace." subtitle="Vos frais professionnels, enfin bien organisés.">
    <form @submit.prevent="submit" class="space-y-5">
      <FormField label="Email" field-id="register-email" v-slot="{ id, describedBy }">
        <input :id="id" v-model="email" type="email" required autocomplete="email" inputmode="email" class="form-control"
          placeholder="vous@exemple.fr" :aria-describedby="describedBy" />
      </FormField>
      <FormField label="Mot de passe" field-id="register-password" hint="8 caractères minimum." :error="passwordError" v-slot="{ id, describedBy, invalid }">
        <input :id="id" v-model="password" type="password" required autocomplete="new-password" minlength="8" class="form-control"
          :aria-describedby="describedBy" :aria-invalid="invalid || undefined" @blur="touched = true" />
      </FormField>

      <AlertBanner v-if="error" variant="danger">{{ error }}</AlertBanner>

      <BaseButton type="submit" block size="lg" :loading="loading">
        {{ loading ? 'Création…' : 'Créer mon compte' }}
      </BaseButton>
    </form>

    <template #footer>
      <p>Déjà un compte ? <RouterLink to="/login" class="btn-link">Se connecter</RouterLink></p>
    </template>
  </AuthCard>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'
import AuthCard from '@/components/auth/AuthCard.vue'
import FormField from '@/components/ui/FormField.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AlertBanner from '@/components/ui/AlertBanner.vue'

const authStore = useAuthStore()
const router = useRouter()
const email = ref('')
const password = ref('')
const touched = ref(false)
const loading = ref(false)
const error = ref('')

const passwordError = computed(() =>
  touched.value && password.value.length > 0 && password.value.length < 8 ? 'Le mot de passe doit contenir au moins 8 caractères.' : undefined
)

async function submit() {
  error.value = ''
  touched.value = true
  if (password.value.length < 8) return
  loading.value = true
  try {
    await authStore.register(email.value, password.value)
    router.push('/')
  } catch (e: any) {
    error.value = e.response?.data?.error ?? 'Une erreur est survenue.'
  } finally {
    loading.value = false
  }
}
</script>
