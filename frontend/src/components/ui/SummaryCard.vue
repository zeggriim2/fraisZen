<template>
  <div class="bg-white rounded-2xl border border-gray-100 p-5">
    <div class="flex items-center gap-3 mb-4">
      <div :class="['w-10 h-10 rounded-xl flex items-center justify-center text-xl', colorBg]">
        <AppIcon :name="iconName" />
      </div>
      <div>
        <p class="font-semibold text-gray-900 text-sm">{{ title }}</p>
        <p class="text-xs text-gray-500">{{ subtitle }}</p>
      </div>
    </div>
    <div class="space-y-2">
      <slot name="details" />
    </div>
    <div class="mt-4 pt-4 border-t border-gray-100 flex justify-between items-center">
      <span class="text-sm text-gray-500">Déduction</span>
      <span :class="['text-2xl font-medium tracking-tight', colorText]">{{ formattedAmount }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import AppIcon from './AppIcon.vue'
import { computed } from 'vue'
import { fmtEur } from '@/utils/formatting'

const props = defineProps<{
  icon: string
  title: string
  subtitle: string
  amount: number
  color: 'blue' | 'emerald' | 'amber' | 'orange' | 'rose'
}>()

const palette: Record<string, { bg: string; text: string }> = {
  blue:    { bg: 'bg-blue-100',    text: 'text-blue-700' },
  emerald: { bg: 'bg-emerald-100', text: 'text-emerald-700' },
  amber:   { bg: 'bg-amber-100',   text: 'text-amber-700' },
  orange:  { bg: 'bg-orange-100',  text: 'text-orange-700' },
  rose:    { bg: 'bg-rose-100',    text: 'text-rose-700' },
}

const iconName = computed(() => ({ blue: 'car', emerald: 'home', amber: 'receipt', orange: 'receipt', rose: 'car' })[props.color])

const colorBg   = computed(() => palette[props.color].bg)
const colorText = computed(() => palette[props.color].text)
const formattedAmount = computed(() => fmtEur(props.amount))
</script>
