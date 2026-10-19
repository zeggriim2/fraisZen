<template>
  <div :class="['empty-state', { 'empty-state-compact': compact }]" :role="role">
    <span v-if="icon" class="empty-state-icon" aria-hidden="true"><AppIcon :name="icon" :size="compact ? 20 : 26" /></span>
    <h3 v-if="title" class="empty-state-title">{{ title }}</h3>
    <p v-if="description || $slots.default" class="empty-state-description"><slot>{{ description }}</slot></p>
    <div v-if="$slots.actions" class="empty-state-actions"><slot name="actions" /></div>
  </div>
</template>

<script setup lang="ts">
import AppIcon from './AppIcon.vue'

withDefaults(defineProps<{
  icon?: string
  title?: string
  description?: string
  compact?: boolean
  role?: string
}>(), { compact: false })
</script>
