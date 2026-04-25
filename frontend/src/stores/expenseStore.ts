import { defineStore } from 'pinia'
import { ref } from 'vue'
import { expenseApi } from '@/api/expenseApi'
import type { CreateExpenseDto, UpdateExpenseDto, Expense, ExpenseSummary } from '@/types'

export const useExpenseStore = defineStore('expense', () => {
  const expenses = ref<Expense[]>([])
  const summary = ref<ExpenseSummary | null>(null)
  const loading = ref(false)

  async function fetchByPeriod(from: string, to: string, personId?: string) {
    loading.value = true
    try {
      expenses.value = await expenseApi.getByPeriod(from, to, personId)
    } finally {
      loading.value = false
    }
  }

  async function fetchSummary(personId: string, year: number) {
    loading.value = true
    try {
      summary.value = await expenseApi.getSummary(personId, year)
    } finally {
      loading.value = false
    }
  }

  async function create(data: CreateExpenseDto) {
    await expenseApi.create(data)
  }

  async function update(id: string, data: UpdateExpenseDto) {
    await expenseApi.update(id, data)
  }

  async function remove(id: string) {
    await expenseApi.remove(id)
    expenses.value = expenses.value.filter(e => e.id !== id)
  }

  return { expenses, summary, loading, fetchByPeriod, fetchSummary, create, update, remove }
})
