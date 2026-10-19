<template>
  <article class="card p-5 flex flex-col">
    <div class="flex items-center gap-3 mb-4">
      <span :class="['grid h-11 w-11 shrink-0 place-items-center rounded-xl', colorBg]" aria-hidden="true">
        <AppIcon :name="iconName" :size="22" />
      </span>
      <div class="min-w-0">
        <h3 class="font-semibold text-ink text-sm">{{ title }}</h3>
        <p class="text-xs text-ink-muted">{{ subtitle }}</p>
      </div>
    </div>
    <div class="space-y-2 flex-1">
      <slot name="details" />
    </div>
    <div class="mt-4 pt-4 border-t border-line flex justify-between items-baseline gap-3">
      <span class="text-sm text-ink-muted">Déduction</span>
      <span :class="['figure text-2xl font-semibold', colorText]">{{ formattedAmount }}</span>
    </div>
  </article>
</template>

<script setup lang="ts">
import AppIcon from './AppIcon.vue'
import { computed } from 'vue'
import { fmtEur } from '@/utils/formatting'

const props = defineProps<{
  /** Icon name from AppIcon (legacy emoji values are ignored). */
  icon: string
  title: string
  subtitle: string
  amount: number
  color: 'blue' | 'emerald' | 'amber' | 'orange' | 'rose'
}>()

const palette: Record<string, { bg: string; text: string; icon: string }> = {
  blue:    { bg: 'bg-blue-100 text-blue-700',       text: 'text-blue-700',    icon: 'car' },
  emerald: { bg: 'bg-emerald-100 text-emerald-700', text: 'text-emerald-700', icon: 'home' },
  amber:   { bg: 'bg-amber-100 text-amber-700',     text: 'text-amber-700',   icon: 'road' },
  orange:  { bg: 'bg-orange-100 text-orange-700',   text: 'text-orange-700',  icon: 'utensils' },
  rose:    { bg: 'bg-rose-100 text-rose-700',       text: 'text-rose-700',    icon: 'parking' },
}

const knownIcons = new Set(['car', 'home', 'road', 'utensils', 'parking', 'receipt', 'wallet'])
const iconName = computed(() => (knownIcons.has(props.icon) ? props.icon : palette[props.color].icon))
const colorBg   = computed(() => palette[props.color].bg)
const colorText = computed(() => palette[props.color].text)
const formattedAmount = computed(() => fmtEur(props.amount))
</script>
