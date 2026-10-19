<template>
  <component :is="as" class="card">
    <header v-if="title || $slots.header || $slots.actions" class="card-header">
      <slot name="header">
        <div class="min-w-0">
          <component :is="titleTag" class="card-title">{{ title }}</component>
          <p v-if="subtitle" class="text-xs text-ink-muted mt-0.5">{{ subtitle }}</p>
        </div>
      </slot>
      <div v-if="$slots.actions" class="flex items-center gap-2 shrink-0"><slot name="actions" /></div>
    </header>
    <div :class="flush ? '' : 'card-body'"><slot /></div>
    <footer v-if="$slots.footer" class="card-footer"><slot name="footer" /></footer>
  </component>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  title?: string
  subtitle?: string
  as?: 'section' | 'article' | 'div'
  titleTag?: 'h2' | 'h3' | 'h4'
  flush?: boolean
}>(), { as: 'section', titleTag: 'h3', flush: false })
</script>
