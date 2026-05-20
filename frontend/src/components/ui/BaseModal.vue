<template>
  <Teleport to="body">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="onBackdropClick">
      <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="onBackdropClick" />
      <div :class="['relative bg-white rounded-2xl shadow-xl w-full flex flex-col max-h-[90vh]', maxWidthClass]">

        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 shrink-0">
          <slot name="header">
            <div>
              <h2 class="text-lg font-semibold text-gray-900">
                <slot name="title" />
              </h2>
              <p v-if="$slots.subtitle" class="text-sm text-gray-500 mt-0.5">
                <slot name="subtitle" />
              </p>
            </div>
          </slot>
          <button @click="$emit('close')" class="p-2 rounded-lg hover:bg-gray-100 shrink-0 ml-4">
            <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <!-- Body -->
        <div class="flex-1 overflow-y-auto px-6 py-5">
          <slot />
        </div>

        <!-- Footer -->
        <div v-if="$slots.footer" class="px-6 py-4 border-t border-gray-200 shrink-0">
          <slot name="footer" />
        </div>

      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
  maxWidth?: 'sm' | 'md' | 'lg' | 'xl'
  closeOnBackdrop?: boolean
}>(), {
  maxWidth: 'lg',
  closeOnBackdrop: true,
})

const emit = defineEmits<{ close: [] }>()

const maxWidthClass = computed(() => ({
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-lg',
  xl: 'max-w-2xl',
}[props.maxWidth]))

function onBackdropClick() {
  if (props.closeOnBackdrop) emit('close')
}
</script>
