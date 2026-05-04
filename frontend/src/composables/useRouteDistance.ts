import { ref } from 'vue'
import { expenseApi } from '@/api/expenseApi'
import { geocodeFirstResult } from '@/composables/useAddressAutocomplete'

export function useRouteDistance() {
  const calculating = ref(false)
  const calcError = ref('')

  async function calculate(departure: string, arrival: string): Promise<number | null> {
    if (!departure.trim() || !arrival.trim()) {
      calcError.value = "Saisissez le départ et l'arrivée."
      return null
    }
    calculating.value = true
    calcError.value = ''
    try {
      const [from, to] = await Promise.all([
        geocodeFirstResult(departure),
        geocodeFirstResult(arrival),
      ])
      if (!from) { calcError.value = `Adresse introuvable : "${departure}"`; return null }
      if (!to)   { calcError.value = `Adresse introuvable : "${arrival}"`; return null }
      return await expenseApi.getDistance(from.lat, from.lng, to.lat, to.lng)
    } catch {
      calcError.value = 'Calcul impossible. Vérifiez les adresses ou votre connexion.'
      return null
    } finally {
      calculating.value = false
    }
  }

  return { calculating, calcError, calculate }
}
