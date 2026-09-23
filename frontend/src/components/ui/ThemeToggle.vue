<template><button class="icon-button" @click="toggle" :aria-label="dark ? 'Activer le mode clair' : 'Activer le mode sombre'" :title="dark ? 'Mode clair' : 'Mode sombre'" :aria-pressed="dark"><AppIcon :name="dark ? 'sun' : 'moon'" /></button></template>
<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import AppIcon from './AppIcon.vue'
const dark = ref(document.documentElement.dataset.theme === 'dark')
const media = window.matchMedia('(prefers-color-scheme: dark)')
function apply(value: boolean) { dark.value = value; document.documentElement.dataset.theme = value ? 'dark' : 'light' }
function toggle() { apply(!dark.value); try { localStorage.setItem('fraiszen-theme', dark.value ? 'dark' : 'light') } catch { /* Theme still works without storage. */ } }
function systemChange() { try { if (localStorage.getItem('fraiszen-theme')) return } catch {} apply(media.matches) }
function storageChange(event: StorageEvent) { if (event.key === 'fraiszen-theme') apply(event.newValue === 'dark' || (!event.newValue && media.matches)) }
onMounted(() => { media.addEventListener('change', systemChange); window.addEventListener('storage', storageChange) })
onUnmounted(() => { media.removeEventListener('change', systemChange); window.removeEventListener('storage', storageChange) })
</script>
