<template>
  <component
    :is="tag"
    :type="tag === 'button' ? type : undefined"
    :to="to"
    :href="href"
    :disabled="tag === 'button' ? disabled || loading : undefined"
    :aria-disabled="tag !== 'button' && (disabled || loading) ? 'true' : undefined"
    :aria-busy="loading ? 'true' : undefined"
    :class="['btn', variantClass, sizeClass, { 'btn-block': block }]"
  >
    <span v-if="loading" class="spinner" aria-hidden="true" />
    <AppIcon v-else-if="icon" :name="icon" :size="size === 'sm' ? 16 : 18" />
    <slot />
    <AppIcon v-if="trailingIcon && !loading" :name="trailingIcon" :size="size === 'sm' ? 16 : 18" />
  </component>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import AppIcon from './AppIcon.vue'

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'danger-soft' | 'brand-soft' | 'link'

const props = withDefaults(defineProps<{
  variant?: Variant
  size?: 'sm' | 'md' | 'lg'
  type?: 'button' | 'submit' | 'reset'
  to?: string
  href?: string
  icon?: string
  trailingIcon?: string
  loading?: boolean
  disabled?: boolean
  block?: boolean
}>(), { variant: 'primary', size: 'md', type: 'button', loading: false, disabled: false, block: false })

const tag = computed(() => (props.to ? RouterLink : props.href ? 'a' : 'button'))
// Literal class names so Tailwind's content scanner keeps them.
const VARIANT_CLASS: Record<Variant, string> = {
  primary: 'btn-primary',
  secondary: 'btn-secondary',
  ghost: 'btn-ghost',
  danger: 'btn-danger',
  'danger-soft': 'btn-danger-soft',
  'brand-soft': 'btn-brand-soft',
  link: 'btn-link',
}
const SIZE_CLASS = { sm: 'btn-sm', md: '', lg: 'btn-lg' } as const
const variantClass = computed(() => VARIANT_CLASS[props.variant])
const sizeClass = computed(() => SIZE_CLASS[props.size])
</script>
