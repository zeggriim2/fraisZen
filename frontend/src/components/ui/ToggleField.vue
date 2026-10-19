<template>
  <div class="flex items-center justify-between gap-4 rounded-xl border border-line bg-surface-muted px-4 py-3">
    <div class="min-w-0">
      <p :id="labelId" class="text-sm font-semibold text-ink">{{ label }}</p>
      <p v-if="description" :id="descriptionId" class="text-xs text-ink-muted mt-0.5">{{ description }}</p>
    </div>
    <button
      type="button"
      role="switch"
      :aria-checked="modelValue"
      :aria-labelledby="labelId"
      :aria-describedby="description ? descriptionId : undefined"
      class="switch"
      :class="[modelValue ? activeColor : 'bg-gray-300', { 'is-on': modelValue }]"
      @click="$emit('update:modelValue', !modelValue)"
    >
      <span class="switch-thumb" />
    </button>
  </div>
</template>

<script setup lang="ts">
import { useId } from 'vue'

withDefaults(defineProps<{
  label: string
  description?: string
  modelValue: boolean
  activeColor?: string
}>(), { activeColor: 'bg-brand-600' })

defineEmits<{ 'update:modelValue': [value: boolean] }>()

const labelId = useId()
const descriptionId = useId()
</script>
