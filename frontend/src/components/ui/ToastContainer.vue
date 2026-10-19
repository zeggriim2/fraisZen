<template>
  <Teleport to="body">
    <div class="toast-region" aria-live="polite" aria-atomic="false">
      <TransitionGroup
        enter-active-class="transition duration-300 ease-out motion-reduce:transition-none"
        enter-from-class="opacity-0 translate-y-2"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-200 ease-in motion-reduce:transition-none"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-2"
      >
        <div
          v-for="toast in toasts"
          :key="toast.id"
          :class="['toast', toastClass(toast.variant)]"
          :role="toast.variant === 'error' ? 'alert' : 'status'"
        >
          <AppIcon :name="iconFor(toast.variant)" :size="18" />
          <span class="flex-1">{{ toast.message }}</span>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { useToast } from '@/composables/useToast'
import AppIcon from './AppIcon.vue'

const { toasts } = useToast()

function toastClass(variant: string): string {
  if (variant === 'success') return 'toast-success'
  if (variant === 'error') return 'toast-error'
  return 'toast-info'
}

function iconFor(variant: string): string {
  if (variant === 'success') return 'check-circle'
  if (variant === 'error') return 'alert-circle'
  return 'info'
}
</script>
