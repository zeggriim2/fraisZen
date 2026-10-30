// Frontend tests of the admin "Configuration fiscale" screen: euros ⇄ cents conversion, forfait 10 %
// form draft → API payload, and the AdminForfaitFields component rendered with Vue's server renderer
// (same technique as taxComparison.test.mjs). Run: npm test (from frontend/).
import { after, before, describe, test } from 'node:test'
import assert from 'node:assert/strict'
import { createServer } from 'vite'
import { createSSRApp, h } from 'vue'
import { renderToString } from 'vue/server-renderer'

let server
let money
let form
let Fields

before(async () => {
  server = await createServer({
    configFile: new URL('../vite.config.ts', import.meta.url).pathname,
    server: { middlewareMode: true, hmr: false, ws: false },
    appType: 'custom',
    logLevel: 'error',
    optimizeDeps: { noDiscovery: true, include: [] },
  })
  money = await server.ssrLoadModule('/src/utils/money.ts')
  form = await server.ssrLoadModule('/src/utils/fiscalConfigForm.ts')
  Fields = (await server.ssrLoadModule('/src/components/admin/AdminForfaitFields.vue')).default
})

after(async () => { await server?.close() })

async function render(component, props) {
  const app = createSSRApp({ render: () => h(component, props) })
  return (await renderToString(app)).replace(/<!--[\s\S]*?-->/g, '')
}

const text = html => html.replace(/<[^>]+>/g, ' ').replace(/&#39;/g, '\'').replace(/&amp;/g, '&').replace(/\s+/g, ' ')

// Reference values (revenus 2025 : 509 € / 14 555 €, 2024 : 504 € / 14 426 €, 2023 : 495 € / 14 171 €) used as fixtures.
const draft2025 = { ratePercent: '10', minimumEuros: '509.00', maximumEuros: '14555.00', version: '2025.1', source: 'BOFiP-IR-BASE-2025', effectiveFrom: '2025-01-01' }

describe('euros ⇄ cents conversion', () => {
  test('converts euro amounts to integer cents without float loss', () => {
    assert.equal(money.eurosToCents(14555), 1_455_500)
    assert.equal(money.eurosToCents('14555.00'), 1_455_500)
    assert.equal(money.eurosToCents('14 555,00'), 1_455_500)
    assert.equal(money.eurosToCents('509'), 50_900)
    assert.equal(money.eurosToCents(14426), 1_442_600)
    assert.equal(money.eurosToCents(14171.1), 1_417_110)
    assert.equal(money.eurosToCents(0.29), 29)
    assert.equal(money.eurosToCents(1.005), 101)
  })

  test('empty or invalid inputs give null', () => {
    assert.equal(money.eurosToCents(''), null)
    assert.equal(money.eurosToCents('  '), null)
    assert.equal(money.eurosToCents(null), null)
    assert.equal(money.eurosToCents(undefined), null)
    assert.equal(money.eurosToCents('abc'), null)
  })

  test('cents are rendered back as euros for the inputs', () => {
    assert.equal(money.centsToEuros(1_455_500), 14555)
    assert.equal(money.centsToEurosInput(1_455_500), '14555.00')
    assert.equal(money.centsToEurosInput(50_900), '509.00')
    assert.equal(money.centsToEurosInput(null), '')
    assert.equal(money.eurosToCents(money.centsToEurosInput(1_417_100)), 1_417_100)
  })
})

describe('forfait draft → API payload', () => {
  test('a complete draft becomes the six API fields with amounts in cents', () => {
    const result = form.buildForfaitInput(draft2025)
    assert.deepEqual(result, {
      ok: true,
      input: { forfaitRatePercent: 10, forfaitMinimumCents: 50_900, forfaitMaximumCents: 1_455_500, version: '2025.1', source: 'BOFiP-IR-BASE-2025', effectiveFrom: '2025-01-01' },
    })
  })

  test('an entirely empty draft is allowed (partial configuration) and sends nothing', () => {
    assert.deepEqual(form.buildForfaitInput(form.emptyForfaitDraft()), { ok: true, input: null })
    assert.equal(form.isForfaitDraftEmpty({ ratePercent: ' ', minimumEuros: '', maximumEuros: '', version: '', source: '', effectiveFrom: '' }), true)
  })

  test('a partially filled draft is refused in French before any API call', () => {
    const result = form.buildForfaitInput({ ...draft2025, source: '', effectiveFrom: '' })
    assert.equal(result.ok, false)
    assert.match(result.error, /renseignés ensemble/)
    assert.match(result.error, /Source, Date d'entrée en vigueur/)
  })

  test('non numeric amounts and non integer rates are refused', () => {
    assert.equal(form.buildForfaitInput({ ...draft2025, maximumEuros: 'abc' }).ok, false)
    assert.equal(form.buildForfaitInput({ ...draft2025, ratePercent: '10.5' }).ok, false)
  })

  test('the draft is rebuilt from the API representation (cents → euros) and compared field by field', () => {
    const config = { year: 2025, remoteWorkDailyAllowance: 2.7, homeMealValue: 5.45, forfaitRatePercent: 10, forfaitMinimumCents: 50_900, forfaitMaximumCents: 1_455_500, version: '2025.1', source: 'BOFiP-IR-BASE-2025', effectiveFrom: '2025-01-01', taxComparisonComplete: true, missingTaxComparisonFields: [] }
    const draft = form.forfaitDraftFromConfig(config)
    assert.deepEqual(draft, draft2025)
    assert.equal(form.isSameForfaitDraft(draft, { ...draft2025, version: ' 2025.1 ' }), true)
    assert.equal(form.isSameForfaitDraft(draft, { ...draft2025, maximumEuros: '14556.00' }), false)

    const partial = form.forfaitDraftFromConfig({ ...config, forfaitRatePercent: null, forfaitMinimumCents: null, forfaitMaximumCents: null, version: null, source: null, effectiveFrom: null })
    assert.deepEqual(partial, form.emptyForfaitDraft())
    assert.deepEqual(form.missingForfaitLabels(['forfait_rate_percent', 'effective_from']), ['Taux (%)', 'Date d\'entrée en vigueur'])
  })
})

describe('AdminForfaitFields component', () => {
  test('renders the six fields for the year with their values (euros, not cents)', async () => {
    const html = await render(Fields, { modelValue: draft2025, label: '2025', idSuffix: '2025', complete: true, missingFields: [] })
    const content = text(html)
    for (const label of ['Taux (%)', 'Minimum (€)', 'Plafond (€)', 'Version', 'Source', 'Date d\'entrée en vigueur']) {
      assert.ok(content.includes(label), label)
    }
    assert.match(html, /id="forfait-rate-2025"[^>]*value="10"/)
    assert.match(html, /id="forfait-minimum-2025"[^>]*value="509\.00"/)
    assert.match(html, /id="forfait-maximum-2025"[^>]*value="14555\.00"/)
    assert.match(html, /id="forfait-version-2025"[^>]*value="2025\.1"/)
    assert.match(html, /id="forfait-source-2025"[^>]*value="BOFiP-IR-BASE-2025"/)
    assert.match(html, /id="forfait-effective-from-2025"[^>]*value="2025-01-01"/)
    assert.ok(content.includes('Comparaison forfait / frais réels disponible pour 2025.'))
    assert.doesNotMatch(html, /role="alert"/)
  })

  test('signals that the comparison is not available for a partial year, listing the missing parameters', async () => {
    const html = await render(Fields, { modelValue: form.emptyForfaitDraft(), label: '2023', idSuffix: '2023', complete: false, missingFields: ['forfait_rate_percent', 'forfait_minimum_cents', 'forfait_maximum_cents', 'version', 'source', 'effective_from'] })
    const content = text(html)
    assert.ok(content.includes('Comparaison forfait / frais réels non disponible pour 2023'))
    assert.ok(content.includes('Taux (%), Minimum (€), Plafond (€), Version, Source, Date d\'entrée en vigueur'))
    assert.match(html, /data-testid="forfait-status-2023"/)
  })

  test('displays the API 422 message inside the form', async () => {
    const html = await render(Fields, { modelValue: draft2025, label: '2025', idSuffix: '2025', complete: false, missingFields: [], error: 'Forfait minimum cannot exceed the maximum.' })
    assert.match(html, /role="alert"[^>]*data-testid="forfait-error-2025"/)
    assert.ok(text(html).includes('Forfait minimum cannot exceed the maximum.'))
  })
})
