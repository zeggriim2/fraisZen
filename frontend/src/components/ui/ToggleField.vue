<template>
  <div class="flex items-center justify-between p-3 rounded-xl border border-gray-200 bg-gray-50">
    <div>
      <p class="text-sm font-medium text-gray-700">{{ label }}</p>
      <p v-if="description" class="text-xs text-gray-500 mt-0.5">{{ description }}</p>
    </div>
    <button
      type="button"
      :aria-label="label"
      :aria-checked="modelValue"
      role="switch"
      @click="$emit('update:modelValue', !modelValue)"
      :class="['relative inline-flex h-6 w-11 items-center rounded-full transition-colors shrink-0', modelValue ? activeColor : 'bg-gray-300']"
    >
      <span :class="['inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform', modelValue ? 'translate-x-6' : 'translate-x-1']" />
    </button>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  label: string
  description?: string
  modelValue: boolean
  activeColor?: string
}>(), { activeColor: 'bg-blue-600' })

defineEmits<{ 'update:modelValue': [value: boolean] }>()
</script>
