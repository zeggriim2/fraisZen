<template>
  <svg
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    :stroke-width="strokeWidth"
    stroke-linecap="round"
    stroke-linejoin="round"
    :aria-hidden="label ? undefined : 'true'"
    :role="label ? 'img' : undefined"
    :aria-label="label || undefined"
    focusable="false"
  >
    <path v-for="(d, i) in resolved" :key="i" :d="d" />
  </svg>
</template>

<script setup lang="ts">
import { computed } from 'vue'

/**
 * Inline SVG icon set (Lucide-style, 24px grid, 1.75 stroke).
 * Icons are decorative by default (aria-hidden); pass `label` to expose a name.
 */
const props = withDefaults(defineProps<{
  name: string
  size?: number | string
  strokeWidth?: number | string
  label?: string
}>(), { size: 20, strokeWidth: 1.75 })

const paths: Record<string, string | string[]> = {
  home: 'm3 10 9-7 9 7v10H3V10Zm6 10v-7h6v7',
  receipt: 'M6 3h12v18l-3-2-3 2-3-2-3 2V3Zm3 5h6m-6 4h6',
  repeat: 'm17 2 4 4-4 4M3 11V8a2 2 0 0 1 2-2h16M7 22l-4-4 4-4m14-1v3a2 2 0 0 1-2 2H3',
  upload: 'M12 16V3m-5 5 5-5 5 5M4 16v5h16v-5',
  download: 'M12 3v13m-5-5 5 5 5-5M4 16v5h16v-5',
  'chevron-left': 'm14 6-6 6 6 6',
  'chevron-right': 'm10 6 6 6-6 6',
  'chevron-down': 'm6 9 6 6 6-6',
  calendar: 'M8 3v4m8-4v4M3 10h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z',
  chart: 'M4 4v16h16M8 15v-4m5 4V7m5 8v-6',
  users: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M9 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8m8 0a4 4 0 0 1 0 8',
  user: 'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2M12 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8',
  settings: 'M4 7h16M4 17h16M8 4v6m8 4v6',
  car: 'm5 6-2 7v6h3v-2h12v2h3v-6l-2-7H5Zm-2 7h18M7 14v1m10-1v1',
  motorcycle: 'M5 18a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm14 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM5 15h4l3-5h4l3 5M12 10 10 7H7m8 0h3l1 3',
  moped: 'M6 18a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm12 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM6 15h7l3-7h3M13 15l-2-5H8',
  bolt: 'M13 2 4 14h7l-1 8 9-12h-7l1-8Z',
  menu: 'M4 6h16M4 12h16M4 18h16',
  arrow: 'M19 12H5m6-6-6 6 6 6',
  'arrow-left': 'M19 12H5m6-6-6 6 6 6',
  'arrow-right': 'M5 12h14m-6-6 6 6-6 6',
  'arrow-up-right': 'M7 17 17 7M8 7h9v9',
  'external-link': 'M14 4h6v6m0-6L10 14M20 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h5',
  logout: 'M9 4H4v16h5m6-12 4 4-4 4m-7-4h11',
  sun: 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8m0-6v2m0 16v2M2 12h2m16 0h2M5 5l1 1m12 12 1 1M5 19l1-1M18 6l1-1',
  moon: 'M20.9 13a9 9 0 0 1-9.9-9.9A9 9 0 1 0 20.9 13Z',
  alert: 'M12 3 2 21h20L12 3Zm0 6v5m0 3v.01',
  'alert-circle': 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-13v5m0 3v.01',
  info: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-5v-5m0-3v.01',
  check: 'm5 12 4 4L19 6',
  'check-circle': 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm-3.5-9 2.5 2.5 5-5',
  x: 'M6 6l12 12M18 6 6 18',
  plus: 'M12 5v14M5 12h14',
  minus: 'M5 12h14',
  search: 'm21 21-4.3-4.3M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z',
  star: 'm12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3 6.4 20.2l1.1-6.2L3 9.6l6.2-.9L12 3Z',
  paperclip: 'm21 11.5-8.6 8.6a5 5 0 0 1-7-7l8.8-8.8a3.3 3.3 0 0 1 4.7 4.7l-8.8 8.8a1.7 1.7 0 0 1-2.4-2.4L15.5 7.7',
  pin: 'M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11Zm0-9a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z',
  key: 'M15 3a6 6 0 0 0-5.7 7.9L3 17.2V21h3.8l1.2-1.2v-2h2l1.2-1.2v-2h2l.9-.9A6 6 0 1 0 15 3Zm2 5v.01',
  file: 'M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Zm0 0v6h6',
  'file-text': 'M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Zm0 0v6h6M8 13h8m-8 4h5',
  'file-spreadsheet': 'M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Zm0 0v6h6M8 13h8M8 17h8m-4-4v4',
  trash: 'M4 7h16M9 7V4h6v3m-7 0 1 13h6l1-13',
  edit: 'M4 20h4l10-10-4-4L4 16v4Zm10-14 4 4',
  copy: 'M9 9h10v12H9V9Zm-4 6H4V3h12v1',
  eye: 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Zm10 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
  refresh: 'M21 12a9 9 0 1 1-3-6.7M21 3v6h-6',
  loader: 'M12 3v3m0 12v3m9-9h-3M6 12H3m15.4-6.4-2.1 2.1M7.7 16.3l-2.1 2.1m12.8 0-2.1-2.1M7.7 7.7 5.6 5.6',
  utensils: 'M4 3v8a3 3 0 0 0 3 3v7M10 3v8a3 3 0 0 1-3 3M18 3c-2 2-3 5-3 8h3v10',
  road: 'M5 3 2 21m17-18 3 18M12 3v4m0 4v3m0 4v3',
  parking: 'M6 21V3h7a5 5 0 0 1 0 10H6',
  wallet: 'M3 7h16a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Zm0 0V5a2 2 0 0 1 2-2h11v4M16 14h.01',
  shield: 'M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z',
  'credit-card': 'M3 6h18v12H3V6Zm0 4h18M7 15h3',
  mail: 'M3 6h18v12H3V6Zm0 0 9 7 9-7',
  lock: 'M6 11h12v10H6V11Zm3 0V7a3 3 0 0 1 6 0v4',
  'map-pin': 'M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11Zm0-9a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z',
  clock: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-13v5l3 2',
  sparkles: 'M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8L12 3ZM5 19l.7 2 .7-2 2-.7-2-.7L5.7 15 5 17l-2 .7 2 .7Z',
  inbox: 'M3 13h5l2 3h4l2-3h5M3 13V6h18v7M3 13v5h18v-5',
  table: 'M3 5h18v14H3V5Zm0 5h18M3 15h18M9 5v14m6-14v14',
}

const resolved = computed(() => {
  const d = paths[props.name] ?? paths.chart
  return Array.isArray(d) ? d : [d]
})
</script>
