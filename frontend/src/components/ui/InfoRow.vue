<template>
  <div class="flex justify-between items-start py-2 border-b border-gray-100 last:border-0">
    <span class="text-sm text-gray-500">{{ label }}</span>
    <span class="text-sm font-medium text-gray-900 text-right max-w-xs">{{ value }}</span>
  </div>
</template>
<script setup lang="ts">
defineProps<{ label: string; value: string }>()
</script>
