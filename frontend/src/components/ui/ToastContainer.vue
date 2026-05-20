<template>
  <Teleport to="body">
    <div class="fixed bottom-6 left-1/2 -translate-x-1/2 z-[100] flex flex-col gap-2 items-center pointer-events-none">
      <TransitionGroup
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="opacity-0 translate-y-2 scale-95"
        enter-to-class="opacity-100 translate-y-0 scale-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="opacity-100 translate-y-0 scale-100"
        leave-to-class="opacity-0 translate-y-2 scale-95"
      >
        <div
          v-for="toast in toasts"
          :key="toast.id"
          :class="[
            'flex items-center gap-2.5 px-4 py-3 rounded-xl shadow-lg text-sm font-medium pointer-events-auto min-w-[200px] max-w-sm',
            toast.variant === 'success' && 'bg-gray-900 text-white',
            toast.variant === 'error'   && 'bg-red-600 text-white',
            toast.variant === 'info'    && 'bg-indigo-600 text-white',
          ]"
        >
          <span v-if="toast.variant === 'success'" class="text-base leading-none">✓</span>
          <span v-else-if="toast.variant === 'error'" class="text-base leading-none">✕</span>
          <span v-else class="text-base leading-none">ℹ</span>
          {{ toast.message }}
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { useToast } from '@/composables/useToast'
const { toasts } = useToast()
</script>
