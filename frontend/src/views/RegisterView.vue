<template>
  <div class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm w-full max-w-sm p-8">
      <div class="mb-8 text-center">
        <h1 class="text-2xl font-bold text-gray-900">Frais Réels</h1>
        <p class="text-sm text-gray-500 mt-1">Créez votre compte</p>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
          <input v-model="email" type="email" required autocomplete="email"
            class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
            placeholder="vous@exemple.fr" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Mot de passe</label>
          <input v-model="password" type="password" required autocomplete="new-password" minlength="8"
            class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
            placeholder="8 caractères minimum" />
        </div>

        <div v-if="error" class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-3 py-2">
          {{ error }}
        </div>

        <button type="submit" :disabled="loading"
          class="w-full py-2.5 px-4 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors">
          {{ loading ? 'Création…' : 'Créer mon compte' }}
        </button>
      </form>

      <p class="mt-6 text-center text-sm text-gray-500">
        Déjà un compte ?
        <RouterLink to="/login" class="text-indigo-600 font-medium hover:text-indigo-700">Se connecter</RouterLink>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/authStore'

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
    await authStore.register(email.value, password.value)
    router.push('/')
  } catch (e: any) {
    error.value = e.response?.data?.error ?? 'Une erreur est survenue.'
  } finally {
    loading.value = false
  }
}
</script>
