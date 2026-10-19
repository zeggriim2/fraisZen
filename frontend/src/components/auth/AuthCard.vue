<template>
  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-card-head">
        <RouterLink to="/login" class="auth-brand" aria-label="FraisZen — accueil">f.</RouterLink>
        <ThemeToggle />
      </div>
      <div class="text-center mb-7">
        <p class="text-xs font-semibold tracking-wide text-brand-700 mb-2">FraisZen · Frais Réels</p>
        <h1 class="text-2xl font-semibold text-ink">{{ title }}</h1>
        <p v-if="subtitle" class="text-sm text-ink-muted mt-2">{{ subtitle }}</p>
      </div>
      <slot />
      <div v-if="$slots.footer" class="mt-6 space-y-2 text-center text-sm text-ink-muted">
        <slot name="footer" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import ThemeToggle from '@/components/ui/ThemeToggle.vue'

defineProps<{ title: string; subtitle?: string }>()
</script>
