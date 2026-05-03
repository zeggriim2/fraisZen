import type { AxiosError } from 'axios'

export function extractApiError(e: unknown, fallback = 'Une erreur est survenue.'): string {
  const axiosErr = e as AxiosError<{ error?: string; message?: string }>
  return axiosErr?.response?.data?.error
    ?? axiosErr?.response?.data?.message
    ?? fallback
}