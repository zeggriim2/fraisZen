import { defineStore } from 'pinia'
import { ref } from 'vue'
import { favoriteRouteApi } from '@/api/favoriteRouteApi'
import type { FavoriteRoute, CreateFavoriteRouteDto } from '@/types'

export const useFavoriteRouteStore = defineStore('favoriteRoute', () => {
  const routes = ref<FavoriteRoute[]>([])
  const loading = ref(false)
  const loadedPersonId = ref<string | null>(null)

  async function fetchByPerson(personId: string, force = false) {
    if (!force && loadedPersonId.value === personId) return
    loading.value = true
    try {
      routes.value = await favoriteRouteApi.getAll(personId)
      loadedPersonId.value = personId
    } finally {
      loading.value = false
    }
  }

  async function create(personId: string, data: CreateFavoriteRouteDto) {
    const route = await favoriteRouteApi.create(personId, data)
    if (loadedPersonId.value === personId) routes.value.push(route)
    return route
  }

  async function update(personId: string, id: string, data: CreateFavoriteRouteDto) {
    const updated = await favoriteRouteApi.update(personId, id, data)
    const idx = routes.value.findIndex(r => r.id === id)
    if (idx !== -1) routes.value[idx] = updated
    return updated
  }

  async function remove(personId: string, id: string) {
    await favoriteRouteApi.remove(personId, id)
    routes.value = routes.value.filter(r => r.id !== id)
  }

  function reset() {
    routes.value = []
    loadedPersonId.value = null
  }

  return { routes, loading, fetchByPerson, create, update, remove, reset }
})
