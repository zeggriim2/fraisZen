<template>
  <fieldset class="forfait-fields" :data-testid="`forfait-fields-${idSuffix}`">
    <legend class="text-xs text-gray-400 font-medium uppercase tracking-wider">Forfait 10 % — comparaison frais réels</legend>

    <div class="forfait-fields-grid">
      <label class="forfait-field">
        <span>Taux (%)</span>
        <input
          :id="`forfait-rate-${idSuffix}`" v-model="model.ratePercent" type="number" inputmode="numeric" min="1" max="100" step="1" placeholder="10"
          class="forfait-input" :aria-label="`Taux du forfait ${label}`"
        />
      </label>
      <label class="forfait-field">
        <span>Minimum (€)</span>
        <input
          :id="`forfait-minimum-${idSuffix}`" v-model="model.minimumEuros" type="number" inputmode="decimal" min="0" step="0.01" placeholder="509,00"
          class="forfait-input" :aria-label="`Minimum du forfait en euros ${label}`"
        />
      </label>
      <label class="forfait-field">
        <span>Plafond (€)</span>
        <input
          :id="`forfait-maximum-${idSuffix}`" v-model="model.maximumEuros" type="number" inputmode="decimal" min="0" step="0.01" placeholder="14555,00"
          class="forfait-input" :aria-label="`Plafond du forfait en euros ${label}`"
        />
      </label>
      <label class="forfait-field">
        <span>Version</span>
        <input
          :id="`forfait-version-${idSuffix}`" v-model="model.version" type="text" maxlength="100" placeholder="2025.1"
          class="forfait-input" :aria-label="`Version de la configuration ${label}`"
        />
      </label>
      <label class="forfait-field">
        <span>Source</span>
        <input
          :id="`forfait-source-${idSuffix}`" v-model="model.source" type="text" maxlength="255" placeholder="BOFiP / article du CGI"
          class="forfait-input" :aria-label="`Source de la configuration ${label}`"
        />
      </label>
      <label class="forfait-field">
        <span>Date d'entrée en vigueur</span>
        <input
          :id="`forfait-effective-from-${idSuffix}`" v-model="model.effectiveFrom" type="date"
          class="forfait-input" :aria-label="`Date d'entrée en vigueur ${label}`"
        />
      </label>
    </div>

    <p v-if="complete" class="forfait-status forfait-status-ok" :data-testid="`forfait-status-${idSuffix}`">
      Comparaison forfait / frais réels disponible pour {{ label }}.
    </p>
    <p v-else class="forfait-status forfait-status-missing" :data-testid="`forfait-status-${idSuffix}`">
      Comparaison forfait / frais réels non disponible pour {{ label }} : paramètres du forfait incomplets<template v-if="missingLabels.length"> ({{ missingLabels.join(', ') }})</template>.
    </p>

    <p v-if="error" role="alert" class="text-xs text-red-500 mt-2" :data-testid="`forfait-error-${idSuffix}`">{{ error }}</p>
  </fieldset>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { missingForfaitLabels, type ForfaitDraft } from '@/utils/fiscalConfigForm'

const props = withDefaults(defineProps<{
  /** Year (or "nouvelle année") used in labels and ids. */
  label: string
  idSuffix: string
  /** Whether the API reports the six parameters as complete for this year (null = not saved yet). */
  complete?: boolean | null
  missingFields?: string[]
  error?: string
}>(), { complete: null, missingFields: () => [], error: '' })

const model = defineModel<ForfaitDraft>({ required: true })

const missingLabels = computed(() => missingForfaitLabels(props.missingFields ?? []))
</script>
