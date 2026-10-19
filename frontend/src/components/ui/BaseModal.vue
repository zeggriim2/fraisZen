<template>
  <Teleport to="body">
    <div class="modal-root" @click.self="onBackdropClick">
      <div class="modal-backdrop" @click="onBackdropClick" />
      <div
        ref="dialog"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        tabindex="-1"
        @keydown="handleKeydown"
        :class="['modal-surface card relative flex w-full flex-col shadow-float', maxWidthClass]"
      >
        <!-- Header -->
        <div class="modal-header">
          <slot name="header">
            <div class="min-w-0">
              <h2 :id="titleId" class="text-lg font-semibold text-ink">
                <slot name="title" />
              </h2>
              <p v-if="$slots.subtitle" class="text-sm text-ink-muted mt-0.5">
                <slot name="subtitle" />
              </p>
            </div>
          </slot>
          <button type="button" class="icon-button is-plain shrink-0 -mr-2" aria-label="Fermer la fenêtre" @click="$emit('close')">
            <AppIcon name="x" />
          </button>
        </div>

        <!-- Body -->
        <div class="modal-body">
          <slot />
        </div>

        <!-- Footer -->
        <div v-if="$slots.footer" class="modal-footer">
          <slot name="footer" />
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted, useId } from 'vue'
import AppIcon from './AppIcon.vue'

const props = withDefaults(defineProps<{
  maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | 'wide'
  closeOnBackdrop?: boolean
}>(), {
  maxWidth: 'lg',
  closeOnBackdrop: true,
})

const dialog = ref<HTMLElement | null>(null)
const titleId = useId()
let previousFocus: HTMLElement | null = null
let previousOverflow = ''
onMounted(() => {
  previousFocus = document.activeElement as HTMLElement | null
  previousOverflow = document.body.style.overflow
  document.body.style.overflow = 'hidden'
  dialog.value?.focus()
})
onUnmounted(() => {
  document.body.style.overflow = previousOverflow
  previousFocus?.focus()
})
function handleKeydown(event: KeyboardEvent) {
  if (event.key === 'Escape') { event.stopPropagation(); emit('close'); return }
  if (event.key !== 'Tab') return
  const elements = Array.from(dialog.value?.querySelectorAll<HTMLElement>('button:not(:disabled), a[href], input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex="0"]') ?? []).filter(el => el.getClientRects().length > 0)
  const first = elements[0]
  const last = elements[elements.length - 1]
  if (!first) { event.preventDefault(); return }
  if (event.shiftKey && (document.activeElement === first || document.activeElement === dialog.value)) { event.preventDefault(); last.focus() }
  else if (!event.shiftKey && (document.activeElement === last || document.activeElement === dialog.value)) { event.preventDefault(); first.focus() }
}

const emit = defineEmits<{ close: [] }>()

const maxWidthClass = computed(() => ({
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-lg',
  xl: 'max-w-2xl',
  wide: 'max-w-4xl',
}[props.maxWidth]))

function onBackdropClick() {
  if (props.closeOnBackdrop) emit('close')
}
</script>
