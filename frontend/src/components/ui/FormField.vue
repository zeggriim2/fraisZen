<template>
  <div class="form-field">
    <label :for="id" class="form-label">
      {{ label }}
      <span v-if="optional" class="optional">(optionnel)</span>
    </label>
    <slot :id="id" :described-by="describedBy" :invalid="!!error" />
    <p v-if="error" :id="`${id}-error`" class="form-error" role="alert">
      <AppIcon name="alert-circle" :size="14" />
      <span>{{ error }}</span>
    </p>
    <p v-else-if="hint" :id="`${id}-hint`" class="form-hint">{{ hint }}</p>
  </div>
</template>

<script setup lang="ts">
import { computed, useId } from 'vue'
import AppIcon from './AppIcon.vue'

/**
 * Accessible field wrapper: visible label bound to the control, hint text and
 * inline error announced via role="alert" and aria-describedby.
 *
 * Usage:
 *   <FormField label="Email" :error="errors.email" v-slot="{ id, describedBy, invalid }">
 *     <input :id="id" :aria-describedby="describedBy" :aria-invalid="invalid || undefined" class="form-control" />
 *   </FormField>
 */
const props = defineProps<{
  label: string
  hint?: string
  error?: string
  optional?: boolean
  fieldId?: string
}>()

const generated = useId()
const id = computed(() => props.fieldId ?? `field-${generated}`)
const describedBy = computed(() => {
  if (props.error) return `${id.value}-error`
  if (props.hint) return `${id.value}-hint`
  return undefined
})
</script>
