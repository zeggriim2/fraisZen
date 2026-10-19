<template>
  <div class="auth-page pricing-page">
    <div class="w-full max-w-3xl">
      <div class="text-center mb-10">
        <span class="eyebrow mb-2">FraisZen · Abonnement</span>
        <h1 class="text-3xl font-semibold text-ink">Choisissez votre offre</h1>
        <p class="mt-2 text-ink-muted">Accédez à toutes les fonctionnalités de Frais Réels</p>
      </div>

      <AlertBanner v-if="error" variant="danger" class="mb-6">{{ error }}</AlertBanner>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <article
          v-for="plan in plans"
          :key="plan.id"
          :class="['card relative flex flex-col p-6', plan.featured && 'ring-2 ring-brand-500 border-transparent']"
        >
          <span v-if="plan.featured" class="badge badge-brand absolute -top-3 left-1/2 -translate-x-1/2">{{ plan.badge }}</span>
          <h2 class="eyebrow">{{ plan.name }}</h2>
          <div class="mt-4 flex items-baseline gap-1">
            <span class="figure text-4xl font-semibold text-ink">{{ plan.price }}</span>
            <span class="text-ink-muted">{{ plan.period }}</span>
          </div>
          <p class="mt-2 text-sm text-ink-muted">{{ plan.note }}</p>
          <ul class="mt-6 space-y-2.5 flex-1">
            <li v-for="f in features" :key="f" class="flex items-start gap-2.5 text-sm text-ink">
              <AppIcon name="check-circle" :size="18" class="shrink-0 text-brand-600 mt-0.5" />{{ f }}
            </li>
          </ul>
          <BaseButton
            class="mt-6"
            block
            size="lg"
            :variant="plan.featured ? 'primary' : 'secondary'"
            :loading="loading === plan.id"
            :disabled="loading !== null && loading !== plan.id"
            @click="subscribe(plan.id)"
          >
            {{ loading === plan.id ? 'Redirection…' : plan.cta }}
          </BaseButton>
        </article>
      </div>

      <p class="text-center text-xs text-ink-subtle mt-8 inline-flex w-full items-center justify-center gap-1.5">
        <AppIcon name="lock" :size="14" />Paiement sécurisé par Stripe · Annulation à tout moment
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { billingApi } from '@/api/billingApi'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import AlertBanner from '@/components/ui/AlertBanner.vue'

type Plan = 'monthly' | 'yearly'

const loading = ref<Plan | null>(null)
const error = ref('')

const features = [
  'Calendrier de trajets illimités',
  'Calcul automatique des frais kilométriques',
  'Récapitulatif annuel par personne',
  'Gestion multi-personnes',
]

const plans: { id: Plan; name: string; price: string; period: string; note: string; cta: string; featured: boolean; badge?: string }[] = [
  { id: 'monthly', name: 'Mensuel', price: '3,99 €', period: '/mois', note: 'Sans engagement', cta: 'S\'abonner', featured: false },
  { id: 'yearly', name: 'Annuel', price: '43,09 €', period: '/an', note: 'soit 3,59 € / mois — 2 mois offerts', cta: 'Choisir l\'offre annuelle', featured: true, badge: '-10 %' },
]

async function subscribe(plan: Plan) {
  error.value = ''
  loading.value = plan
  try {
    const { url } = await billingApi.createCheckout(plan)
    window.location.href = url
  } catch {
    error.value = 'Impossible d\'ouvrir le paiement pour le moment. Veuillez réessayer.'
  } finally {
    loading.value = null
  }
}
</script>
