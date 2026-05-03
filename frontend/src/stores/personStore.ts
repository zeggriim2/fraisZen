import { defineStore } from 'pinia'
import { ref } from 'vue'
import { personApi } from '@/api/personApi'
import { extractApiError } from '@/utils/apiError'
import type { Person } from '@/types'

export const usePersonStore = defineStore('person', () => {
  const persons = ref<Person[]>([])
  const activePerson = ref<Person | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchAll() {
    loading.value = true
    error.value = null
    try {
      persons.value = await personApi.getAll()
      if (!activePerson.value && persons.value.length > 0) {
        activePerson.value = persons.value[0]
      }
    } catch (e: unknown) {
      error.value = extractApiError(e)
    } finally {
      loading.value = false
    }
  }

  async function create(data: Omit<Person, 'id' | 'fullName' | 'createdAt'>) {
    const p = await personApi.create(data)
    persons.value.push(p)
    if (!activePerson.value) activePerson.value = p
    return p
  }

  async function update(id: string, data: Omit<Person, 'id' | 'fullName' | 'createdAt'>) {
    const updated = await personApi.update(id, data)
    const idx = persons.value.findIndex(p => p.id === id)
    if (idx !== -1) persons.value[idx] = updated
    if (activePerson.value?.id === id) activePerson.value = updated
  }

  async function remove(id: string) {
    await personApi.remove(id)
    persons.value = persons.value.filter(p => p.id !== id)
    if (activePerson.value?.id === id) activePerson.value = persons.value[0] ?? null
  }

  function setActive(person: Person) { activePerson.value = person }

  async function toggleFavorite(id: string) {
    const person = persons.value.find(p => p.id === id)
    if (!person) return
    const { fullName: _fn, createdAt: _ca, id: _id, ...rest } = person
    await update(id, { ...rest, favorite: !person.favorite })
  }

  return { persons, activePerson, loading, error, fetchAll, create, update, remove, setActive, toggleFavorite }
})
