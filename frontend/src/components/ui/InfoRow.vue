<template>
  <div class="flex flex-wrap justify-between items-start gap-x-4 gap-y-0.5 py-2.5 border-b border-line last:border-0">
    <span class="text-sm text-ink-muted">{{ label }}</span>
    <span class="text-sm font-medium text-ink text-right tabular max-w-xs break-words">{{ value }}</span>
  </div>
</template>
<script setup lang="ts">
defineProps<{ label: string; value: string }>()
</script>
