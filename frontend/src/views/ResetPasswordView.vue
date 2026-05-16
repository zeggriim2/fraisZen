<template>
  <div class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm w-full max-w-sm p-8">
      <div class="mb-8 text-center">
        <h1 class="text-2xl font-bold text-gray-900">Nouveau mot de passe</h1>
        <p class="text-sm text-gray-500 mt-1">Choisissez un nouveau mot de passe pour votre compte.</p>
      </div>

      <div v-if="!token" class="text-center text-sm text-red-600">
        Lien invalide. Veuillez faire une nouvelle
        <RouterLink to="/forgot-password" class="text-indigo-600 font-medium hover:text-indigo-700">demande de réinitialisation</RouterLink>.
      </div>

      <form v-else-if="!done" @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nouveau mot de passe</label>
          <input v-model="newPassword" type="password" required autocomplete="new-password"
            class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
            minlength="8" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Confirmer le mot de passe</label>
          <input v-model="confirm" type="password" required autocomplete="new-password"
            class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
        </div>

        <div v-if="error" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
          {{ error }}
        </div>

        <button type="submit" :disabled="loading"
          class="w-full py-2.5 px-4 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors">
          {{ loading ? 'Enregistrement…' : 'Enregistrer le mot de passe' }}
        </button>
      </form>

      <div v-else class="text-center space-y-4">
        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mx-auto">
          <svg class="w-6 h-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <p class="text-sm text-gray-700">Mot de passe modifié avec succès.</p>
        <RouterLink to="/login" class="block w-full py-2.5 px-4 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 text-center transition-colors">
          Se connecter
        </RouterLink>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRoute } from 'vue-router'
import { authApi } from '@/api/authApi'

const route = useRoute()
const token = ref((route.query.token as string) ?? '')
const newPassword = ref('')
const confirm = ref('')
const loading = ref(false)
const error = ref('')
const done = ref(false)

async function submit() {
  error.value = ''
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
