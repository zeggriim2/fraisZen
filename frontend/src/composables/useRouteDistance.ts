import { ref } from 'vue'

interface Coords { lat: number; lon: number }

async function geocodeAddress(q: string): Promise<Coords | null> {
  const url = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(q)}&format=json&limit=1&countrycodes=fr`
  const res = await fetch(url, {
    headers: { 'Accept-Language': 'fr', 'User-Agent': 'fraisReelImpot/1.0 (contact@fraisreel.fr)' },
  })
  if (!res.ok) return null
  const data = await res.json() as Array<{ lat: string; lon: string }>
  if (!data.length) return null
  return { lat: parseFloat(data[0].lat), lon: parseFloat(data[0].lon) }
}

async function fetchRouteDistanceKm(from: Coords, to: Coords): Promise<number> {
  const url = `https://router.project-osrm.org/route/v1/driving/${from.lon},${from.lat};${to.lon},${to.lat}?overview=false`
  const res = await fetch(url)
  if (!res.ok) throw new Error('routing failed')
  const data = await res.json() as { code: string; routes: Array<{ distance: number }> }
  if (data.code !== 'Ok' || !data.routes?.length) throw new Error('no route')
  // Convert meters → km, rounded to 0.1
  return Math.round(data.routes[0].distance / 100) / 10
}

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
      const [fromC, toC] = await Promise.all([
        geocodeAddress(departure),
        geocodeAddress(arrival),
      ])
      if (!fromC) { calcError.value = `Adresse introuvable : "${departure}"`; return null }
      if (!toC) { calcError.value = `Adresse introuvable : "${arrival}"`; return null }
      return await fetchRouteDistanceKm(fromC, toC)
    } catch {
      calcError.value = 'Calcul impossible. Vérifiez les adresses.'
      return null
    } finally {
      calculating.value = false
    }
  }

  return { calculating, calcError, calculate }
}
