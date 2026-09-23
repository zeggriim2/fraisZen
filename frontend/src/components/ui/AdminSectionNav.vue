<template>
  <nav class="admin-section-nav" aria-label="Sections de l’administration">
    <RouterLink v-for="item in items" :key="item.to" :to="item.to" :class="{ active: route.path === item.to || route.path.startsWith(item.to + '/') }"><AppIcon :name="item.icon" />{{ item.label }}</RouterLink>
  </nav>
</template>
<script setup lang="ts">
import { useRoute } from 'vue-router'
import AppIcon from './AppIcon.vue'
const route = useRoute()
const items = [
  { to: '/admin/dashboard', label: 'Vue d’ensemble', icon: 'chart' },
  { to: '/admin/users', label: 'Utilisateurs', icon: 'users' },
  { to: '/admin/fiscal-config', label: 'Fiscalité', icon: 'settings' },
  { to: '/admin/bareme-kilometrique', label: 'Barèmes km', icon: 'car' },
]
</script>
