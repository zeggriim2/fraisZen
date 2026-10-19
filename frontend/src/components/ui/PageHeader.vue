<template>
  <header class="page-header">
    <div class="min-w-0">
      <span v-if="eyebrow" class="eyebrow mb-1">{{ eyebrow }}</span>
      <component :is="as" class="page-header-title">{{ title }}</component>
      <p v-if="description" class="page-header-description">{{ description }}</p>
    </div>
    <div v-if="$slots.actions" class="page-header-actions">
      <slot name="actions" />
    </div>
  </header>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  title: string
  eyebrow?: string
  description?: string
  as?: 'h1' | 'h2'
}>(), { as: 'h2' })
</script>
