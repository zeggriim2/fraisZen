<template>
  <div class="min-h-screen bg-gray-50 flex items-center justify-center p-6">
    <div class="max-w-2xl w-full">
      <div class="text-center mb-10">
        <h1 class="text-3xl font-bold text-gray-900">Choisissez votre offre</h1>
        <p class="mt-2 text-gray-500">Accédez à toutes les fonctionnalités de Frais Réels</p>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <!-- Mensuel -->
        <div class="bg-white rounded-2xl border border-gray-200 p-6 flex flex-col">
          <p class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Mensuel</p>
          <div class="mt-4 flex items-end gap-1">
            <span class="text-4xl font-bold text-gray-900">3,99 €</span>
            <span class="text-gray-400 mb-1">/mois</span>
          </div>
          <p class="mt-2 text-sm text-gray-400">Sans engagement</p>
          <ul class="mt-6 space-y-2 flex-1">
            <li v-for="f in features" :key="f" class="flex items-start gap-2 text-sm text-gray-600">
              <span class="text-indigo-500 font-bold shrink-0">✓</span>{{ f }}
            </li>
          </ul>
          <button
            @click="subscribe('monthly')"
            :disabled="loading === 'monthly'"
            class="mt-6 w-full py-2.5 px-4 bg-indigo-600 text-white rounded-xl text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors"
          >
            {{ loading === 'monthly' ? 'Redirection…' : 'S\'abonner' }}
          </button>
        </div>

        <!-- Annuel -->
        <div class="bg-white rounded-2xl border-2 border-indigo-500 p-6 flex flex-col relative">
          <span class="absolute -top-3 left-1/2 -translate-x-1/2 bg-indigo-500 text-white text-xs font-semibold px-3 py-1 rounded-full">
            -10 %
          </span>
          <p class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Annuel</p>
          <div class="mt-4 flex items-end gap-1">
            <span class="text-4xl font-bold text-gray-900">43,09 €</span>
            <span class="text-gray-400 mb-1">/an</span>
          </div>
          <p class="mt-2 text-sm text-gray-400">soit 3,59 € / mois — 2 mois offerts</p>
          <ul class="mt-6 space-y-2 flex-1">
            <li v-for="f in features" :key="f" class="flex items-start gap-2 text-sm text-gray-600">
              <span class="text-indigo-500 font-bold shrink-0">✓</span>{{ f }}
            </li>
          </ul>
          <button
            @click="subscribe('yearly')"
            :disabled="loading === 'yearly'"
            class="mt-6 w-full py-2.5 px-4 bg-indigo-600 text-white rounded-xl text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 transition-colors"
          >
            {{ loading === 'yearly' ? 'Redirection…' : 'Choisir l\'offre annuelle' }}
          </button>
        </div>
      </div>

      <p class="text-center text-xs text-gray-400 mt-8">
        Paiement sécurisé par Stripe · Annulation à tout moment
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { billingApi } from '@/api/billingApi'

const loading = ref<'monthly' | 'yearly' | null>(null)

const features = [
  'Calendrier de trajets illimités',
  'Calcul automatique des frais kilométriques',
  'Récapitulatif annuel par personne',
  'Gestion multi-personnes',
]

async function subscribe(plan: 'monthly' | 'yearly') {
  loading.value = plan
  try {
    const { url } = await billingApi.createCheckout(plan)
    window.location.href = url
  } finally {
    loading.value = null
  }
}
</script>
