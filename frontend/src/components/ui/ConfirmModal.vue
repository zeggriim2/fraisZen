<template>
  <Teleport to="body">
    <div class="modal-root">
      <div class="modal-backdrop" @click="$emit('cancel')" />
      <div
        ref="dialog"
        role="alertdialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        :aria-describedby="messageId"
        tabindex="-1"
        class="modal-surface card relative w-full max-w-sm p-6 shadow-float"
        @keydown.esc.stop="$emit('cancel')"
      >
        <div v-if="icon" :class="['mx-auto mb-4 grid h-12 w-12 place-items-center rounded-full', iconBg]">
          <AppIcon :name="variant === 'danger' ? 'alert' : 'info'" :size="24" />
        </div>
        <h3 :id="titleId" class="text-lg font-semibold text-ink text-center mb-2">{{ title }}</h3>
        <p :id="messageId" class="text-sm text-ink-muted text-center mb-6">{{ message }}</p>
        <div class="grid grid-cols-2 gap-3">
          <button type="button" class="btn btn-secondary" @click="$emit('cancel')">{{ cancelLabel }}</button>
          <button ref="confirmButton" type="button" :class="['btn', variant === 'danger' ? 'btn-danger' : 'btn-primary']" @click="$emit('confirm')">{{ confirmLabel }}</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, useId } from 'vue'
import AppIcon from './AppIcon.vue'

const props = withDefaults(defineProps<{
  title: string
  message: string
  confirmLabel?: string
  cancelLabel?: string
  variant?: 'danger' | 'default'
  icon?: boolean
}>(), { confirmLabel: 'Confirmer', cancelLabel: 'Annuler', variant: 'default', icon: false })

defineEmits<{ confirm: []; cancel: [] }>()

const titleId = useId()
const messageId = useId()
const dialog = ref<HTMLElement | null>(null)
const confirmButton = ref<HTMLButtonElement | null>(null)
let previousFocus: HTMLElement | null = null

onMounted(() => {
  previousFocus = document.activeElement as HTMLElement | null
  // Destructive dialogs focus the safe action first; default dialogs focus the primary action.
  if (props.variant === 'danger') dialog.value?.focus()
  else confirmButton.value?.focus()
})
onUnmounted(() => { previousFocus?.focus() })

const iconBg = computed(() =>
  props.variant === 'danger' ? 'bg-danger-soft text-danger-ink' : 'bg-brand-50 text-brand-600'
)
</script>
