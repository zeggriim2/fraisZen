<template>
  <div class="relative">
    <input
      :value="modelValue"
      :placeholder="placeholder"
      type="text"
      class="w-full rounded-lg border-gray-300 shadow-sm text-sm"
      @input="onInput"
      @blur="onBlur"
      @keydown.escape="open = false"
    />
    <div
      v-if="open && suggestions.length"
      class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg max-h-48 overflow-y-auto"
    >
      <button
        v-for="s in suggestions"
        :key="s.label"
        type="button"
        class="w-full px-3 py-2 text-left text-sm text-gray-800 hover:bg-indigo-50 border-b border-gray-100 last:border-0 truncate"
        @mousedown.prevent="select(s)"
      >
        {{ s.label }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useAddressAutocomplete } from '@/composables/useAddressAutocomplete'
import type { AddressSuggestion } from '@/composables/useAddressAutocomplete'

defineProps<{ modelValue: string; placeholder?: string }>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
  'select': [suggestion: AddressSuggestion]
}>()

const { suggestions, search, reset } = useAddressAutocomplete()
const open = ref(false)

function onInput(e: Event) {
  const val = (e.target as HTMLInputElement).value
  emit('update:modelValue', val)
  search(val)
  open.value = true
}

function select(s: AddressSuggestion) {
  emit('update:modelValue', s.label)
  emit('select', s)
  reset()
  open.value = false
}

function onBlur() {
  setTimeout(() => { open.value = false }, 150)
}
</script>
