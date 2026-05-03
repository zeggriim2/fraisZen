import { defineStore } from 'pinia'
import { ref } from 'vue'
import { expenseApi } from '@/api/expenseApi'
import { extractApiError } from '@/utils/apiError'
import type { CreateExpenseDto, UpdateExpenseDto, Expense, ExpenseSummary } from '@/types'

export const useExpenseStore = defineStore('expense', () => {
  const expenses = ref<Expense[]>([])
  const summary = ref<ExpenseSummary | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function fetchByPeriod(from: string, to: string, personId?: string) {
    loading.value = true
    error.value = null
    try {
      expenses.value = await expenseApi.getByPeriod(from, to, personId)
    } catch (e: unknown) {
      error.value = extractApiError(e)
    } finally {
      loading.value = false
    }
  }

  async function fetchSummary(personId: string, year: number) {
    loading.value = true
    error.value = null
    try {
      summary.value = await expenseApi.getSummary(personId, year)
    } catch (e: unknown) {
      error.value = extractApiError(e)
    } finally {
      loading.value = false
    }
  }

  async function create(data: CreateExpenseDto): Promise<string> {
    const resp = await expenseApi.create(data)
    return (resp.data as { id: string }).id
  }

  async function update(id: string, data: UpdateExpenseDto) {
    await expenseApi.update(id, data)
  }

  async function remove(id: string) {
    await expenseApi.remove(id)
    expenses.value = expenses.value.filter(e => e.id !== id)
  }

  return { expenses, summary, loading, error, fetchByPeriod, fetchSummary, create, update, remove }
})
