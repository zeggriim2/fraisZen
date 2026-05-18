import http from './http'
import type { Person, FavoriteRoute, CreateFavoriteRouteDto } from '@/types'

export const personApi = {
  getAll: () => http.get<Person[]>('/persons').then(r => r.data),
  create: (data: Omit<Person, 'id' | 'fullName' | 'createdAt'>) =>
    http.post<Person>('/persons', data).then(r => r.data),
  update: (id: string, data: Omit<Person, 'id' | 'fullName' | 'createdAt'>) =>
    http.put<Person>(`/persons/${id}`, data).then(r => r.data),
  remove: (id: string) => http.delete(`/persons/${id}`),

  getFavoriteRoutes: (personId: string) =>
    http.get<FavoriteRoute[]>(`/persons/${personId}/favorite-routes`).then(r => r.data),
  createFavoriteRoute: (personId: string, data: CreateFavoriteRouteDto) =>
    http.post<FavoriteRoute[]>(`/persons/${personId}/favorite-routes`, data).then(r => r.data),
  deleteFavoriteRoute: (personId: string, routeId: string) =>
    http.delete(`/persons/${personId}/favorite-routes/${routeId}`),
}
