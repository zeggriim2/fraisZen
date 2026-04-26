<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="$emit('close')">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">

      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <div>
          <h2 class="text-lg font-semibold text-gray-900">Import relevé bancaire</h2>
          <p class="text-sm text-gray-500 mt-0.5">Détection automatique des péages et repas</p>
        </div>
        <button @click="$emit('close')" class="p-2 rounded-lg hover:bg-gray-100">
          <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <div class="flex-1 overflow-y-auto px-6 py-5 space-y-5">

        <!-- Step 1: file upload -->
        <div v-if="!detectedRows.length">
          <div
            class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-indigo-400 transition-colors cursor-pointer"
            @click="fileInput?.click()"
            @dragover.prevent
            @drop.prevent="handleDrop"
          >
            <div class="text-4xl mb-3">📄</div>
            <p class="text-sm font-medium text-gray-700">Déposez votre fichier CSV ou cliquez pour sélectionner</p>
            <p class="text-xs text-gray-500 mt-1">BNP, Société Générale, LCL, Boursorama, N26, Revolut…</p>
            <input ref="fileInput" type="file" accept=".csv,.txt" class="hidden" @change="handleFileInput" />
          </div>
          <div v-if="parseError" class="mt-3 text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">{{ parseError }}</div>
          <div class="mt-4 bg-gray-50 border border-gray-200 rounded-xl p-4 text-xs text-gray-600 space-y-1">
            <p class="font-medium text-gray-700">Formats supportés</p>
            <p>• Séparateur <code class="bg-gray-200 px-1 rounded">;</code> (banques françaises) ou <code class="bg-gray-200 px-1 rounded">,</code> (fintechs)</p>
            <p>• Colonnes détectées automatiquement : date, libellé, montant</p>
            <p>• Seules les lignes avec montant négatif (dépenses) sont importées</p>
          </div>
        </div>

        <!-- Step 2: review detected rows -->
        <div v-else>
          <div class="flex items-center justify-between mb-3">
            <p class="text-sm font-medium text-gray-700">
              {{ selectedCount }} / {{ detectedRows.length }} frais sélectionnés
            </p>
            <div class="flex gap-2">
              <button @click="selectAll(true)" class="text-xs text-indigo-600 hover:underline">Tout sélectionner</button>
              <span class="text-gray-300">|</span>
              <button @click="selectAll(false)" class="text-xs text-gray-500 hover:underline">Tout désélectionner</button>
              <span class="text-gray-300">|</span>
              <button @click="reset" class="text-xs text-gray-500 hover:underline">Nouveau fichier</button>
            </div>
          </div>

          <div class="bg-amber-50 border border-amber-100 rounded-xl px-4 py-3 mb-4 text-xs text-amber-700">
            Seuls les <strong>péages</strong> et <strong>repas professionnels</strong> sont détectés. Vérifiez les montants et ajustez le type si besoin.
          </div>

          <div class="space-y-2">
            <div
              v-for="(row, i) in detectedRows" :key="i"
              :class="['flex items-center gap-3 p-3 rounded-xl border transition-colors',
                selected[i] ? 'border-indigo-200 bg-indigo-50/40' : 'border-gray-200 bg-gray-50 opacity-60']"
            >
              <input type="checkbox" v-model="selected[i]" class="rounded text-indigo-600 cursor-pointer" />
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="text-xs font-mono text-gray-500">{{ row.displayDate }}</span>
                  <span class="text-sm text-gray-800 truncate font-medium">{{ row.description }}</span>
                </div>
              </div>
              <div class="flex items-center gap-2 shrink-0">
                <span class="text-sm font-semibold text-gray-800">{{ row.amount.toFixed(2) }} €</span>
                <select
                  v-model="row.type"
                  :class="['text-xs rounded-lg border px-2 py-1 font-medium', typeSelectClass(row.type)]"
                >
                  <option value="toll">🛣️ Péage</option>
                  <option value="meal">🍽️ Repas</option>
                </select>
              </div>
            </div>
          </div>

          <div v-if="importError" class="mt-3 text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg">{{ importError }}</div>
        </div>

      </div>

      <div v-if="detectedRows.length" class="px-6 py-4 border-t border-gray-200 flex gap-3">
        <button @click="$emit('close')" class="flex-1 px-4 py-2 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Annuler</button>
        <button @click="importSelected" :disabled="importing || selectedCount === 0" class="flex-1 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 rounded-lg text-sm font-medium text-white">
          {{ importing ? 'Import en cours…' : `Importer ${selectedCount} frais` }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useExpenseStore } from '@/stores/expenseStore'
import { usePersonStore } from '@/stores/personStore'

const emit = defineEmits<{ close: []; imported: [count: number] }>()

const expenseStore = useExpenseStore()
const personStore = usePersonStore()

const fileInput = ref<HTMLInputElement | null>(null)
const parseError = ref('')
const importError = ref('')
const importing = ref(false)

interface DetectedRow {
  parsedDate: string   // YYYY-MM-DD
  displayDate: string
  description: string
  amount: number
  type: 'toll' | 'meal'
}

const detectedRows = ref<DetectedRow[]>([])
const selected = ref<boolean[]>([])

const selectedCount = computed(() => selected.value.filter(Boolean).length)

const TOLL_KEYWORDS = /VINCI|ASF|SANEF|COFIROUTE|ESCOTA|ADELAC|AREA|APRR|SAPN|ULYS|LIBER.?T|AUTOROUTE|P[EÉ]AGE|TOLL|ALIS[IA]|ATLANDES/i
const MEAL_KEYWORDS = /RESTAURANT|RESTAU|BRASSERIE|BISTROT|PIZZ|SUSHI|MCDONALD|MCDO|BURGER KING|QUICK|KFC|KEBAB|NOODLES|RAMEN|TRAITEUR|CANTINE|CAFET[ÉE]RIA|CAF[ÉE]|TABAC|BOULANGERIE|PATISSERIE|SANDWICHERIE|PAUL |ERIC KAYSER|BRIOCHE DOR[ÉE]E|LUNCH/i

function detectType(desc: string): 'toll' | 'meal' | null {
  if (TOLL_KEYWORDS.test(desc)) return 'toll'
  if (MEAL_KEYWORDS.test(desc)) return 'meal'
  return null
}

function parseFrDate(s: string): string | null {
  // DD/MM/YYYY
  const m1 = s.match(/^(\d{2})\/(\d{2})\/(\d{4})$/)
  if (m1) return `${m1[3]}-${m1[2]}-${m1[1]}`
  // YYYY-MM-DD (with optional time)
  const m2 = s.match(/^(\d{4})-(\d{2})-(\d{2})/)
  if (m2) return `${m2[1]}-${m2[2]}-${m2[3]}`
  // DD-MM-YYYY
  const m3 = s.match(/^(\d{2})-(\d{2})-(\d{4})$/)
  if (m3) return `${m3[3]}-${m3[2]}-${m3[1]}`
  return null
}

function parseAmount(s: string): number | null {
  // Remove spaces, replace comma decimal separator
  const cleaned = s.replace(/\s/g, '').replace(',', '.')
  const n = parseFloat(cleaned)
  return isNaN(n) ? null : n
}

function parseCSV(text: string): string[][] {
  const sep = text.includes(';') ? ';' : ','
  return text
    .split(/\r?\n/)
    .filter(l => l.trim())
    .map(l => l.split(sep).map(c => c.trim().replace(/^"|"$/g, '')))
}

function findColumnIndex(headers: string[], patterns: RegExp): number {
  return headers.findIndex(h => patterns.test(h))
}

function processCsv(text: string) {
  parseError.value = ''
  const rows = parseCSV(text)
  if (rows.length < 2) { parseError.value = 'Fichier vide ou non reconnu.'; return }

  const headers = rows[0].map(h => h.toLowerCase())
  const dataRows = rows.slice(1)

  const dateIdx = findColumnIndex(headers, /date/)
  const descIdx = findColumnIndex(headers, /libell[eé]|description|label|payee|r[eé]f[eé]rence|motif/)
  const amountIdx = findColumnIndex(headers, /montant|amount|d[eé]bit|somme/)

  // Fallback: heuristic detection on first data row
  const guessedDateIdx = dateIdx >= 0 ? dateIdx : (() => {
    return dataRows[0].findIndex(c => parseFrDate(c) !== null)
  })()
  const guessedAmountIdx = amountIdx >= 0 ? amountIdx : (() => {
    return dataRows[0].findIndex(c => {
      const v = parseAmount(c)
      return v !== null && v !== 0
    })
  })()
  const guessedDescIdx = descIdx >= 0 ? descIdx : (() => {
    const exclude = new Set([guessedDateIdx, guessedAmountIdx])
    return dataRows[0].findIndex((c, i) => !exclude.has(i) && c.length > 3 && isNaN(parseFloat(c.replace(',', '.'))))
  })()

  if (guessedDateIdx < 0 || guessedAmountIdx < 0 || guessedDescIdx < 0) {
    parseError.value = 'Impossible de détecter les colonnes (date, libellé, montant). Vérifiez le format.'
    return
  }

  const results: DetectedRow[] = []
  for (const row of dataRows) {
    if (row.length <= Math.max(guessedDateIdx, guessedAmountIdx, guessedDescIdx)) continue
    const rawDate = row[guessedDateIdx]
    const parsedDate = parseFrDate(rawDate)
    if (!parsedDate) continue
    const amount = parseAmount(row[guessedAmountIdx])
    if (amount === null || amount >= 0) continue  // only expenses (negative)
    const description = row[guessedDescIdx]
    if (!description) continue
    const type = detectType(description)
    if (!type) continue
    results.push({
      parsedDate,
      displayDate: rawDate.split(' ')[0],
      description,
      amount: Math.abs(amount),
      type,
    })
  }

  if (!results.length) {
    parseError.value = 'Aucun péage ou repas professionnel détecté dans ce fichier.'
    return
  }

  detectedRows.value = results
  selected.value = results.map(() => true)
}

function handleDrop(e: DragEvent) {
  const file = e.dataTransfer?.files[0]
  if (file) readFile(file)
}

function handleFileInput(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (file) readFile(file)
}

function readFile(file: File) {
  const reader = new FileReader()
  reader.onload = (e) => processCsv(e.target?.result as string)
  reader.readAsText(file, 'utf-8')
}

function selectAll(val: boolean) {
  selected.value = selected.value.map(() => val)
}

function reset() {
  detectedRows.value = []
  selected.value = []
  parseError.value = ''
  if (fileInput.value) fileInput.value.value = ''
}

function typeSelectClass(type: 'toll' | 'meal'): string {
  return type === 'toll'
    ? 'border-amber-200 bg-amber-50 text-amber-700'
    : 'border-orange-200 bg-orange-50 text-orange-700'
}

async function importSelected() {
  if (!personStore.activePerson) {
    importError.value = 'Sélectionnez une personne avant d\'importer.'
    return
  }
  importing.value = true
  importError.value = ''
  let count = 0
  try {
    const toImport = detectedRows.value.filter((_, i) => selected.value[i])
    for (const row of toImport) {
      if (row.type === 'toll') {
        await expenseStore.create({ type: 'toll', personId: personStore.activePerson.id, date: row.parsedDate, amount: row.amount, description: row.description })
      } else {
        await expenseStore.create({ type: 'meal', personId: personStore.activePerson.id, date: row.parsedDate, mealAmount: row.amount, description: row.description })
      }
      count++
    }
    emit('imported', count)
  } catch {
    importError.value = 'Une erreur est survenue lors de l\'import.'
  } finally {
    importing.value = false
  }
}
</script>
