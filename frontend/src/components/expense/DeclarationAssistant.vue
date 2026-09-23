<template>
  <section class="declaration-assistant" aria-labelledby="declaration-assistant-title">
    <header class="declaration-assistant-header">
      <div>
        <span class="eyebrow">ASSISTANT DE DÉCLARATION</span>
        <h3 id="declaration-assistant-title">Déclaration {{ year + 1 }} · revenus {{ year }}</h3>
        <p>Une année de dépenses est déclarable pendant la campagne fiscale de l’année suivante.</p>
      </div>
      <span :class="['declaration-status', ready ? 'is-ready' : 'is-warning']">
        {{ ready ? 'Dossier prêt' : `${missing.length} point${missing.length > 1 ? 's' : ''} à vérifier` }}
      </span>
    </header>

    <div class="declaration-steps">
      <article class="declaration-step">
        <span class="step-number">01</span>
        <div class="step-content">
          <h4>Identifiez le déclarant</h4>
          <p>{{ personName }}</p>
          <label for="tax-case">Position dans la déclaration</label>
          <select id="tax-case" :value="taxCase" @change="$emit('update:taxCase', ($event.target as HTMLSelectElement).value)">
            <option v-for="option in taxCases" :key="option.value" :value="option.value">{{ option.label }} — case {{ option.value }}</option>
          </select>
        </div>
      </article>

      <article class="declaration-step">
        <span class="step-number">02</span>
        <div class="step-content">
          <h4>Contrôlez le montant</h4>
          <strong class="declaration-amount">{{ formattedTotal }}</strong>
          <p>À reporter dans la <b>case {{ taxCase }}</b> lors de la campagne {{ year + 1 }}.</p>
          <ul v-if="missing.length" class="declaration-warnings">
            <li v-for="item in missing" :key="item">{{ item }}</li>
          </ul>
          <p v-else class="declaration-valid">✓ Les données nécessaires au calcul sont disponibles.</p>
        </div>
      </article>

      <article class="declaration-step declaration-copy-step">
        <span class="step-number">03</span>
        <div class="step-content">
          <h4>Préparez votre annexe</h4>
          <p class="declaration-copy">{{ declarationText }}</p>
          <button class="quiet-button" type="button" @click="copyText">{{ copied ? 'Texte copié ✓' : 'Copier le texte' }}</button>
        </div>
      </article>
    </div>

    <footer class="declaration-actions">
      <p>Les exports incluent la personne, l’année fiscale, la case sélectionnée et le détail des calculs.</p>
      <div>
        <button class="quiet-button" type="button" :disabled="!ready || csvLoading" @click="$emit('downloadCsv')">
          <AppIcon name="chart" />{{ csvLoading ? 'Génération…' : 'Télécharger le CSV' }}
        </button>
        <button class="primary-action" type="button" :disabled="!ready || pdfLoading" @click="$emit('downloadPdf')">
          <AppIcon name="receipt" />{{ pdfLoading ? 'Génération…' : 'Générer le dossier PDF' }}
        </button>
      </div>
    </footer>
  </section>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import { fmtEur } from '@/utils/formatting'

const props = defineProps<{
  personName: string
  year: number
  total: number
  taxCase: string
  ready: boolean
  missing: string[]
  pdfLoading: boolean
  csvLoading: boolean
}>()

defineEmits<{
  'update:taxCase': [value: string]
  downloadPdf: []
  downloadCsv: []
}>()

const copied = ref(false)
const taxCases = [
  { value: '1AK', label: 'Déclarant 1' },
  { value: '1BK', label: 'Déclarant 2' },
  { value: '1CK', label: 'Personne à charge 1' },
  { value: '1DK', label: 'Personne à charge 2' },
]
const formattedTotal = computed(() => fmtEur(props.total))
const declarationText = computed(() => `Frais réels de ${props.personName} pour les revenus ${props.year} : ${formattedTotal.value} à reporter en case ${props.taxCase}. Ce montant comprend les frais professionnels enregistrés dans FraisZen et détaillés dans le dossier joint.`)

async function copyText() {
  try {
    await navigator.clipboard.writeText(declarationText.value)
  } catch {
    const textarea = document.createElement('textarea')
    textarea.value = declarationText.value
    textarea.style.position = 'fixed'
    textarea.style.opacity = '0'
    document.body.appendChild(textarea)
    textarea.select()
    document.execCommand('copy')
    textarea.remove()
  }
  copied.value = true
  window.setTimeout(() => { copied.value = false }, 2000)
}
</script>
