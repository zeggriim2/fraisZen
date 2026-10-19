<template>
  <AuthCard title="Vos frais, enfin sous contrôle." subtitle="Connectez-vous pour préparer votre déclaration avec sérénité.">
    <form @submit.prevent="submit" class="space-y-5">
      <FormField label="Email" field-id="login-email" v-slot="{ id, describedBy }">
        <input :id="id" v-model="email" type="email" required autocomplete="email" inputmode="email" class="form-control"
          placeholder="vous@exemple.fr" :aria-describedby="describedBy" :aria-invalid="error ? 'true' : undefined" />
      </FormField>
      <FormField label="Mot de passe" field-id="login-password" v-slot="{ id, describedBy }">
        <input :id="id" v-model="password" type="password" required autocomplete="current-password" class="form-control"
          :aria-describedby="describedBy" :aria-invalid="error ? 'true' : undefined" />
      </FormField>

      <AlertBanner v-if="error" variant="danger">{{ error }}</AlertBanner>

      <BaseButton type="submit" block size="lg" :loading="loading">
        {{ loading ? 'Connexion…' : 'Se connecter' }}
      </BaseButton>
    </form>

    <template #footer>
      <p><RouterLink to="/forgot-password" class="btn-link">Mot de passe oublié ?</RouterLink></p>
      <p>Pas encore de compte ? <RouterLink to="/register" class="btn-link">S'inscrire</RouterLink></p>
    </template>
  </AuthCard>
</template>

<script setup lang="ts">
import { ref } from 'vue'
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
const loading = ref(false)
const error = ref('')

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await authStore.login(email.value, password.value)
    router.push('/')
  } catch {
    error.value = 'Email ou mot de passe incorrect.'
  } finally {
    loading.value = false
  }
}
</script>
