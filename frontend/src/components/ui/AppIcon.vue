<template><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="paths[name] || paths.chart" /></svg></template>
<script setup lang="ts">
defineProps<{ name: string }>()
const paths: Record<string, string> = {
 home: 'm3 10 9-7 9 7v10H3V10Zm6 10v-7h6v7',
 receipt: 'M6 3h12v18l-3-2-3 2-3-2-3 2V3Zm3 5h6m-6 4h6',
 repeat: 'm17 2 4 4-4 4M3 11V8a2 2 0 0 1 2-2h16M7 22l-4-4 4-4m14-1v3a2 2 0 0 1-2 2H3',
 upload: 'M12 16V3m-5 5 5-5 5 5M4 16v5h16v-5',
 'chevron-left': 'm14 6-6 6 6 6',
 'chevron-right': 'm10 6 6 6-6 6',
 calendar: 'M8 3v4m8-4v4M3 10h18M5 5h14a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z',
 chart: 'M4 4v16h16M8 15v-4m5 4V7m5 8v-6',
 users: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M9 3a4 4 0 1 0 0 8 4 4 0 0 0 0-8m8 0a4 4 0 0 1 0 8',
 settings: 'M4 7h16M4 17h16M8 4v6m8 4v6',
 car: 'm5 6-2 7v6h3v-2h12v2h3v-6l-2-7H5Zm-2 7h18M7 14v1m10-1v1',
 menu: 'M4 6h16M4 12h16M4 18h16',
 arrow: 'M19 12H5m6-6-6 6 6 6',
 logout: 'M9 4H4v16h5m6-12 4 4-4 4m-7-4h11',
 sun: 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8m0-6v2m0 16v2M2 12h2m16 0h2M5 5l1 1m12 12 1 1M5 19l1-1M18 6l1-1',
 moon: 'M20.9 13a9 9 0 0 1-9.9-9.9A9 9 0 1 0 20.9 13Z',
}
</script>
