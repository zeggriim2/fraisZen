<template>
  <div class="loading-state" role="status" aria-live="polite">
    <span class="spinner spinner-lg" aria-hidden="true" />
    <p class="text-sm text-ink-muted">{{ label }}</p>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ label?: string }>(), { label: 'Chargement…' })
</script>
