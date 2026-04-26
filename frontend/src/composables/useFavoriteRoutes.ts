import { ref } from 'vue'
import type { VehicleType } from '@/types'

export interface FavoriteRoute {
  id: string
  name: string
  departure: string
  arrival: string
  vehicleType: VehicleType
  vehiclePower: number
  isElectric: boolean
  roundTrip: boolean
}

const STORAGE_KEY = 'frais_reel_favorites'

function loadFromStorage(): FavoriteRoute[] {
  try {
    return JSON.parse(localStorage.getItem(STORAGE_KEY) ?? '[]')
  } catch {
    return []
  }
}

function persist(routes: FavoriteRoute[]) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(routes))
}

export function useFavoriteRoutes() {
  const favorites = ref<FavoriteRoute[]>(loadFromStorage())

  function saveFavorite(route: Omit<FavoriteRoute, 'id'>) {
    const entry: FavoriteRoute = { ...route, id: crypto.randomUUID() }
    favorites.value = [...favorites.value, entry]
    persist(favorites.value)
  }

  function removeFavorite(id: string) {
    favorites.value = favorites.value.filter(f => f.id !== id)
    persist(favorites.value)
  }

  return { favorites, saveFavorite, removeFavorite }
}
