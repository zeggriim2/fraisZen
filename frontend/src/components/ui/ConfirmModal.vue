<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="$emit('cancel')" />
      <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-sm p-6">
        <div v-if="icon" class="w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-4" :class="iconBg">
          <svg v-if="variant === 'danger'" class="w-6 h-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
          </svg>
        </div>
        <h3 class="text-base font-semibold text-gray-900 text-center mb-2">{{ title }}</h3>
        <p class="text-sm text-gray-500 text-center mb-6">{{ message }}</p>
        <div class="flex gap-3">
          <button
            @click="$emit('cancel')"
            class="flex-1 px-4 py-2 border border-gray-200 text-gray-700 rounded-xl text-sm font-medium hover:bg-gray-50 transition-colors"
          >
            {{ cancelLabel }}
          </button>
          <button
            @click="$emit('confirm')"
            :class="['flex-1 px-4 py-2 rounded-xl text-sm font-medium transition-colors', confirmClass]"
          >
            {{ confirmLabel }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  title: string
  message: string
  confirmLabel?: string
  cancelLabel?: string
  variant?: 'danger' | 'default'
  icon?: boolean
}>()

defineEmits<{ confirm: []; cancel: [] }>()

const confirmClass = computed(() =>
  props.variant === 'danger'
    ? 'bg-red-600 text-white hover:bg-red-700'
    : 'bg-indigo-600 text-white hover:bg-indigo-700'
)
const iconBg = computed(() =>
  props.variant === 'danger' ? 'bg-red-50' : 'bg-indigo-50'
)
</script>
