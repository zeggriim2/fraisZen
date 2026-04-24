import { defineStore } from 'pinia'
import { ref } from 'vue'
import { personApi } from '@/api/personApi'
import type { Person } from '@/types'

export const usePersonStore = defineStore('person', () => {
  const persons = ref<Person[]>([])
  const activePerson = ref<Person | null>(null)
  const loading = ref(false)

  async function fetchAll() {
    loading.value = true
    try {
      persons.value = await personApi.getAll()
      if (!activePerson.value && persons.value.length > 0) {
        activePerson.value = persons.value[0]
      }
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

  return { persons, activePerson, loading, fetchAll, create, update, remove, setActive }
})
