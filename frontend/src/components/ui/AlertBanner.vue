<template>
  <div :class="['alert', VARIANT_CLASS[variant]]" :role="variant === 'danger' || variant === 'warning' ? 'alert' : 'status'">
    <AppIcon :name="iconName" :size="18" />
    <div class="min-w-0 flex-1">
      <p v-if="title" class="font-semibold">{{ title }}</p>
      <div :class="{ 'mt-0.5': title }"><slot /></div>
    </div>
    <slot name="actions" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import AppIcon from './AppIcon.vue'

const props = withDefaults(defineProps<{
  variant?: 'danger' | 'warning' | 'success' | 'info'
  title?: string
}>(), { variant: 'info' })

const VARIANT_CLASS = { danger: 'alert-danger', warning: 'alert-warning', success: 'alert-success', info: 'alert-info' } as const
const iconName = computed(() => ({
  danger: 'alert-circle',
  warning: 'alert',
  success: 'check-circle',
  info: 'info',
})[props.variant])
</script>
