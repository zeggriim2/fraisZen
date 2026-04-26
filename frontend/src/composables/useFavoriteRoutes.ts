import { computed, watch } from 'vue'
import type { Ref } from 'vue'
import { useFavoriteRouteStore } from '@/stores/favoriteRouteStore'
import type { CreateFavoriteRouteDto } from '@/types'

export type { FavoriteRoute } from '@/types'

export function useFavoriteRoutes(personId: Ref<string | undefined>) {
  const store = useFavoriteRouteStore()

  watch(personId, async (id) => {
    if (id) await store.fetchByPerson(id)
    else store.reset()
  }, { immediate: true })

  const favorites = computed(() => store.routes)

  async function saveFavorite(data: CreateFavoriteRouteDto) {
    if (!personId.value) return
    await store.create(personId.value, data)
  }

  async function updateFavorite(id: string, data: CreateFavoriteRouteDto) {
    if (!personId.value) return
    await store.update(personId.value, id, data)
  }

  async function removeFavorite(id: string) {
    if (!personId.value) return
    await store.remove(personId.value, id)
  }

  return { favorites, saveFavorite, updateFavorite, removeFavorite, loading: computed(() => store.loading) }
}
