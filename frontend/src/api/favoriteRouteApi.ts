import http from './http'
import type { FavoriteRoute, CreateFavoriteRouteDto } from '@/types'

export const favoriteRouteApi = {
  getAll: (personId: string) =>
    http.get<FavoriteRoute[]>(`/persons/${personId}/favorite-routes`).then(r => r.data),

  create: (personId: string, data: CreateFavoriteRouteDto) =>
    http.post<FavoriteRoute>(`/persons/${personId}/favorite-routes`, data).then(r => r.data),

  update: (personId: string, id: string, data: CreateFavoriteRouteDto) =>
    http.put<FavoriteRoute>(`/persons/${personId}/favorite-routes/${id}`, data).then(r => r.data),

  remove: (personId: string, id: string) =>
    http.delete(`/persons/${personId}/favorite-routes/${id}`),
}
