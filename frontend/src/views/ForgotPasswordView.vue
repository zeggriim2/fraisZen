<template>
  <div class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm w-full max-w-sm p-8">
      <div class="mb-8 text-center">
        <h1 class="text-2xl font-bold text-gray-900">Mot de passe oublié</h1>
        <p class="text-sm text-gray-500 mt-1">Saisissez votre email pour recevoir un lien de réinitialisation.</p>
      </div>

      <form v-if="!sent" @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input v-model="email" type="email" required autocomplete="email"
            class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
            placeholder="vous@exemple.fr" />
        </div>

        <div v-if="error" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
          {{ error }}
        </div>

        <button type="submit" :disabled="loading"
          class="w-full py-2.5 px-4 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors">
          {{ loading ? 'Envoi…' : 'Envoyer le lien' }}
        </button>
      </form>

      <div v-else class="text-center space-y-3">
        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto">
          <svg class="w-6 h-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <p class="text-sm text-gray-700">
          Si un compte existe pour <strong>{{ email }}</strong>, vous recevrez un email avec un lien valable 24 heures.
        </p>
      </div>

      <p class="mt-6 text-center text-sm text-gray-500">
        <RouterLink to="/login" class="text-indigo-600 font-medium hover:text-indigo-700">Retour à la connexion</RouterLink>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { authApi } from '@/api/authApi'

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
