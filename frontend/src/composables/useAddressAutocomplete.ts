import { ref } from 'vue'

export interface AddressSuggestion {
  label: string
  lat: number
  lng: number
}

export async function geocodeFirstResult(query: string): Promise<{ lat: number; lng: number } | null> {
  if (query.trim().length < 3) return null
  try {
    const url = `https://api-adresse.data.gouv.fr/search/?q=${encodeURIComponent(query)}&limit=1`
    const res = await fetch(url)
    if (!res.ok) return null
    const data = await res.json() as { features: Array<{ geometry: { coordinates: [number, number] } }> }
    if (!data.features.length) return null
    const [lng, lat] = data.features[0].geometry.coordinates
    return { lat, lng }
  } catch {
    return null
  }
}

export function useAddressAutocomplete() {
  const suggestions = ref<AddressSuggestion[]>([])
  let debounceTimer: ReturnType<typeof setTimeout> | null = null

  function search(query: string): void {
    if (debounceTimer) clearTimeout(debounceTimer)
    if (query.trim().length < 3) {
      suggestions.value = []
      return
    }
    debounceTimer = setTimeout(async () => {
      try {
        const url = `https://api-adresse.data.gouv.fr/search/?q=${encodeURIComponent(query)}&limit=5`
        const res = await fetch(url)
        if (!res.ok) { suggestions.value = []; return }
        const data = await res.json() as {
          features: Array<{
            properties: { label: string }
            geometry: { coordinates: [number, number] }
          }>
        }
        suggestions.value = data.features.map(f => ({
          label: f.properties.label,
          lat: f.geometry.coordinates[1],
          lng: f.geometry.coordinates[0],
        }))
      } catch {
        suggestions.value = []
      }
    }, 300)
  }

  function reset(): void {
    if (debounceTimer) clearTimeout(debounceTimer)
    suggestions.value = []
  }

  return { suggestions, search, reset }
}