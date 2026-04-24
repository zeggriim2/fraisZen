import axios from 'axios'
import type { Person } from '@/types'

const http = axios.create({ baseURL: '/api' })

export const personApi = {
  getAll: () => http.get<Person[]>('/persons').then(r => r.data),
  create: (data: Omit<Person, 'id' | 'fullName' | 'createdAt'>) =>
    http.post<Person>('/persons', data).then(r => r.data),
  update: (id: string, data: Omit<Person, 'id' | 'fullName' | 'createdAt'>) =>
    http.put<Person>(`/persons/${id}`, data).then(r => r.data),
  remove: (id: string) => http.delete(`/persons/${id}`),
}
