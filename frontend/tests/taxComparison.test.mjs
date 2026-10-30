// Frontend tests of the forfait / real expenses comparison UI.
// No browser and no extra dependency: components are compiled by Vite (same config as the app) and
// rendered with Vue's server renderer; assertions run on the produced HTML.
// Run: npm test (from frontend/).
import { after, before, describe, test } from 'node:test'
import assert from 'node:assert/strict'
import { createServer } from 'vite'
import { createSSRApp, h } from 'vue'
import { renderToString } from 'vue/server-renderer'
import { createMemoryHistory, createRouter } from 'vue-router'

const PERSON_A = '11111111-1111-4111-8111-111111111111'
const PERSON_B = '22222222-2222-4222-8222-222222222222'

let server
let Result
let Editor
let utils
let anchors
let fr

before(async () => {
  server = await createServer({
    configFile: new URL('../vite.config.ts', import.meta.url).pathname,
    server: { middlewareMode: true, hmr: false, ws: false },
    appType: 'custom',
    logLevel: 'error',
    optimizeDeps: { noDiscovery: true, include: [] },
  })
  Result = (await server.ssrLoadModule('/src/components/tax/TaxComparisonResult.vue')).default
  Editor = (await server.ssrLoadModule('/src/components/tax/TaxInputsEditor.vue')).default
  utils = await server.ssrLoadModule('/src/utils/taxComparison.ts')
  anchors = await server.ssrLoadModule('/src/utils/fieldAnchor.ts')
  fr = (await server.ssrLoadModule('/src/i18n/locales/fr.json')).default
})

after(async () => { await server?.close() })

async function render(component, props) {
  const app = createSSRApp({ render: () => h(component, props) })
  app.use(createRouter({ history: createMemoryHistory(), routes: [{ path: '/:any(.*)*', component: { render: () => null } }] }))
  return (await renderToString(app)).replace(/<!--[\s\S]*?-->/g, '')
}

const text = html => html.replace(/<[^>]+>/g, ' ').replace(/&#39;/g, '\'').replace(/&amp;/g, '&').replace(/\s+/g, ' ')

function comparison({ personId = PERSON_A, year = 2026, status = 'available', difference = 123_456, display = {}, warnings = [], closure = {} } = {}) {
  const available = status === 'available' || status === 'closed'
  const rounded = Math.sign(difference) * Math.floor((Math.abs(difference) + 50) / 100)
  return {
    status,
    personId,
    year,
    amounts: available ? {
      netTaxableSalaryCents: 3_000_000, forfaitRatePercent: 10, theoreticalForfaitCents: 300_000, forfaitMinimumCents: 50_000,
      forfaitMaximumCents: 1_400_000, forfaitLimitApplied: 'none', forfaitCents: 300_000, realExpensesCents: 300_000 + difference,
      reintegratedReimbursementsCents: 0, excludedAlreadyDeductedContributionCents: 0, baseForfaitCents: 2_700_000,
      baseRealExpensesCents: 2_700_000 - difference, differenceCents: difference,
    } : null,
    decision: null,
    missing: [],
    rules: available ? [{ code: 'forfait_rate_percent', value: 10, version: '2026.1', source: 'BOFiP-IR-BASE-2026', effectiveFrom: '2026-01-01' }] : [],
    configuration: { year, version: '2026.1', source: 'BOFiP-IR-BASE-2026', effectiveFrom: '2026-01-01' },
    warnings: [],
    origins: {
      netTaxableSalary: { entries: 1, employers: ['ACME'] },
      realExpenses: { entries: 3, trips: 2, travelAllowanceCents: 12_345 },
      reimbursements: { entries: 1, reintegrated: 1 },
    },
    display: {
      temporalQualification: 'provisional_estimate',
      showBanner: available && difference > 0,
      outcome: available ? (difference > 0 ? 'advantageous' : difference < 0 ? 'less_advantageous' : 'equivalent') : null,
      roundedDifferenceEuros: available ? rounded : null,
      differenceBelowOneEuro: available && difference !== 0 && Math.abs(difference) < 100,
      missingFields: [],
      warnings,
      disclaimerKey: 'tax.comparison.disclaimer',
      ...display,
    },
    closure: {
      state: status === 'closed' ? 'closed' : 'open',
      closable: status === 'available',
      calculationFingerprint: 'f'.repeat(64),
      version: status === 'closed' ? 1 : null,
      closedAt: status === 'closed' ? '2027-02-10T09:30:00+00:00' : null,
      snapshotFingerprint: status === 'closed' ? 'f'.repeat(64) : null,
      afterClosure: status === 'closed'
        ? { policy: 'snapshot_kept_reopen_to_recalculate', dataChanged: false, currentStatus: 'available', currentDifferenceCents: difference, currentRoundedDifferenceEuros: rounded }
        : null,
      history: status === 'closed' ? [{ version: 1, state: 'closed', closedAt: '2027-02-10T09:30:00+00:00', reopenedAt: null }] : [],
      ...closure,
    },
  }
}

const BANNER_TITLE = 'Les frais réels pourraient réduire votre revenu imposable'

describe('banner and recommendation', () => {
  test('a strictly positive difference shows the banner with the rounded estimated taxable income difference and "Comprendre le calcul"', async () => {
    const html = await render(Result, { comparison: comparison({ difference: 123_456 }), baseId: 'c' })
    const content = text(html)
    assert.match(html, /data-testid="tax-banner"/)
    assert.ok(content.includes(BANNER_TITLE))
    assert.match(content, /Écart estimé de revenu imposable : 1\s?235\s?€/u)
    assert.ok(content.includes('Comprendre le calcul'))
    assert.match(html, /aria-controls="c-detail"/)
    assert.doesNotMatch(content.toLowerCase(), /économie d.impôt|gain fiscal/u)
  })

  test('a positive difference below 1 euro displays "écart inférieur à 1 €"', async () => {
    const content = text(await render(Result, { comparison: comparison({ difference: 99 }), baseId: 'c' }))
    assert.ok(content.includes('Écart estimé de revenu imposable : écart inférieur à 1 €'))
    assert.doesNotMatch(content, /0\s?€/u)
  })

  test('a zero difference shows no recommendation and states that both options give the same deduction', async () => {
    const html = await render(Result, { comparison: comparison({ difference: 0 }), baseId: 'c', initiallyOpen: true })
    const content = text(html)
    assert.doesNotMatch(html, /data-testid="tax-banner"/)
    assert.ok(!content.includes(BANNER_TITLE))
    // In the summary AND in the detail.
    assert.equal(content.split('produisent actuellement la même déduction').length - 1, 2)
  })

  test('a negative difference never recommends real expenses', async () => {
    for (const difference of [-1, -60, -98_765]) {
      const html = await render(Result, { comparison: comparison({ difference }), baseId: 'c' })
      const content = text(html)
      assert.doesNotMatch(html, /data-testid="tax-banner"/)
      assert.ok(!content.includes(BANNER_TITLE))
      assert.ok(content.includes('l’option frais réels n’est pas recommandée'))
    }
  })
})

describe('unavailable results', () => {
  test('configuration_missing says that the fiscal configuration must be completed, without a link to a nonexistent field', async () => {
    const result = comparison({
      status: 'configuration_missing',
      display: {
        missingFields: [
          { code: 'fiscal_config[2026]', field: 'fiscal_config', params: {}, link: null },
          { code: 'fiscal_config[2026].forfait_rate_percent', field: 'fiscal_config_forfait_rate_percent', params: {}, link: null },
        ],
      },
    })
    const html = await render(Result, { comparison: result, baseId: 'c' })
    const content = text(html)
    assert.ok(content.includes('La configuration fiscale de l’année 2026 doit être complétée'))
    assert.ok(content.includes('Configuration fiscale de l’année 2026'))
    assert.ok(content.includes('Taux du forfait (configuration fiscale 2026)'))
    assert.doesNotMatch(html, /<a /)
    assert.doesNotMatch(html, /data-testid="tax-banner"/)
  })

  test('incomplete lists exactly the missing information, each with a direct link to its field', async () => {
    const query = `personId=${PERSON_A}&amp;year=2025`
    const result = comparison({
      status: 'incomplete',
      year: 2025,
      display: {
        temporalQualification: 'annual_estimate_to_verify',
        missingFields: [
          { code: 'net_taxable_salary[2025]', field: 'net_taxable_salary', params: {}, link: { path: '/persons', query: { personId: PERSON_A, year: '2025' }, hash: `#tax-field-net-taxable-salary-${PERSON_A}` } },
          { code: 'employer_reimbursement[r1].to_reintegrate', field: 'employer_reimbursement_to_reintegrate', params: { reimbursementId: 'r1' }, link: { path: '/persons', query: { personId: PERSON_A, year: '2025' }, hash: '#tax-field-employer-reimbursement-r1' } },
          { code: 'employer_reimbursement[r2].expense[e9]', field: 'employer_reimbursement_expense', params: { reimbursementId: 'r2', expenseId: 'e9' }, link: { path: '/persons', query: { personId: PERSON_A, year: '2025' }, hash: '#tax-field-employer-reimbursement-expense-r2' } },
        ],
      },
    })
    const html = await render(Result, { comparison: result, baseId: 'c' })
    const content = text(html)
    assert.equal(html.match(/data-testid="tax-missing-field"/g).length, 3)
    assert.ok(content.includes('Le calcul n’est pas possible tant que ces informations manquent'))
    assert.ok(content.includes('Salaire net imposable de l’année 2025'))
    assert.ok(content.includes('Indiquer si le remboursement employeur r1 doit être réintégré'))
    assert.ok(content.includes('La dépense e9 liée au remboursement employeur r2 est introuvable'))
    const hrefs = [...html.matchAll(/<a [^>]*href="([^"]+)"/g)].map(m => m[1])
    assert.deepEqual(hrefs, [
      `/persons?${query}#tax-field-net-taxable-salary-${PERSON_A}`,
      `/persons?${query}#tax-field-employer-reimbursement-r1`,
      `/persons?${query}#tax-field-employer-reimbursement-expense-r2`,
    ])
    assert.doesNotMatch(html, /data-testid="tax-banner"/)
  })
})

describe('temporal qualification and disclaimer', () => {
  test('current year is a provisional estimate, an elapsed year an annual estimate to verify', async () => {
    const current = text(await render(Result, { comparison: comparison(), baseId: 'c' }))
    const elapsed = text(await render(Result, { comparison: comparison({ year: 2025, display: { temporalQualification: 'annual_estimate_to_verify' } }), baseId: 'c' }))
    assert.ok(current.includes('Estimation provisoire : l’année 2026 n’est pas terminée'))
    assert.ok(elapsed.includes('Estimation annuelle à vérifier : l’année 2025 est échue mais n’est pas clôturée'))
  })

  test('the mandatory fiscal disclaimer is displayed with the card', async () => {
    const content = text(await render(Result, { comparison: comparison(), baseId: 'c' }))
    for (const mandatory of ['Avertissement fiscal', 'estimation indicative', 'ne calcule pas votre impôt', 'ne constitue pas un conseil fiscal', 'impots.gouv.fr', 'responsable de votre déclaration']) {
      assert.ok(content.includes(mandatory), mandatory)
    }
  })
})

describe('calculation detail', () => {
  test('shows amounts, formulas, rules, version, source, effective date, origins, translated warnings and the disclaimer', async () => {
    const result = comparison({
      difference: 50_000,
      warnings: [
        { code: 'reimbursements_not_reintegrated', params: { count: '2' } },
        { code: 'meal_contribution_not_counted_twice', params: { reimbursementId: 'r1', amountCents: '500', expenseId: 'e1' } },
      ],
    })
    const html = await render(Result, { comparison: result, baseId: 'c', initiallyOpen: true })
    const content = text(html)
    for (const label of [
      'Salaire net imposable', 'Forfait théorique (10 % du salaire)', 'Minimum du forfait', 'Plafond du forfait', 'Forfait retenu',
      'Frais réels', 'Remboursements employeur réintégrés', 'Base imposable avec le forfait', 'Base imposable avec les frais réels',
      'Écart estimé de revenu imposable', 'Formules', 'Version des règles', '2026.1', 'Source', 'BOFiP-IR-BASE-2026',
      'Date d’entrée en vigueur', '2026-01-01', 'Origine des montants', 'employeur(s) : ACME', '2 trajet(s) valorisé(s)',
      'Indemnité kilométrique annuelle calculée', 'Avertissement fiscal',
      '2 remboursement(s) employeur non marqué(s) à réintégrer',
      'Remboursement r1 : 5,00',
    ]) {
      assert.ok(content.includes(label), label)
    }
    assert.match(content, /30\s?000,00\s?€/u, 'net taxable salary')
    assert.match(content, /500,00\s?€/u, 'exact difference in the detail')
    // Engine warnings are never displayed as raw (English) text.
    assert.doesNotMatch(html, /lang="en"/)
    assert.doesNotMatch(content, /reimbursement\(s\)|not marked|were not/)
  })

  test('an unknown warning code still renders a French sentence, never a raw key', () => {
    assert.ok(utils.warningLabel({ code: 'something_new', params: {} }).startsWith('Un point d’attention'))
    assert.ok(utils.warningLabel({ code: 'unknown', params: {} }).startsWith('Un point d’attention'))
  })
})

describe('isolation between persons', () => {
  test('a late response for another person or year is never displayed', () => {
    assert.equal(utils.isCurrentResponse({ personId: PERSON_A, year: 2026 }, { personId: PERSON_A, year: 2026 }), true)
    assert.equal(utils.isCurrentResponse({ personId: PERSON_A, year: 2026 }, { personId: PERSON_B, year: 2026 }), false)
    assert.equal(utils.isCurrentResponse({ personId: PERSON_A, year: 2025 }, { personId: PERSON_A, year: 2026 }), false)
  })

  test('each rendered result is tagged with its own person and shows only its own amounts', async () => {
    const a = await render(Result, { comparison: comparison({ personId: PERSON_A, difference: 10_000 }), baseId: 'a' })
    const b = await render(Result, { comparison: comparison({ personId: PERSON_B, difference: -10_000 }), baseId: 'b' })
    assert.match(a, new RegExp(`data-person-id="${PERSON_A}"`))
    assert.doesNotMatch(a, new RegExp(PERSON_B))
    assert.match(b, new RegExp(`data-person-id="${PERSON_B}"`))
    assert.match(text(a), /Écart estimé de revenu imposable : 100\s?€/u)
    assert.doesNotMatch(b, /data-testid="tax-banner"/)
  })
})

describe('direct links reach a field', () => {
  const inputs = {
    personId: PERSON_A,
    year: 2026,
    salaries: [],
    reimbursements: [
      { id: 'r1', employer: 'ACME', year: 2026, date: null, amountCents: 5_000, category: 'transport', toReintegrate: null, expenseId: 'e9', linkedExpenseFound: false },
      { id: 'r2', employer: 'ACME', year: 2026, date: '2026-03-01', amountCents: 1_000, category: 'meal', toReintegrate: true, expenseId: null, linkedExpenseFound: null },
    ],
    categories: ['transport', 'meal', 'mileage', 'remote_work', 'other'],
  }

  test('the fiscal data editor renders the exact DOM ids targeted by the comparison links', async () => {
    const html = await render(Editor, { inputs, personId: PERSON_A, year: 2026 })
    // Salary field (link net_taxable_salary), reintegration select (to_reintegrate), linked expense action (expense).
    assert.match(html, new RegExp(`<input id="tax-field-net-taxable-salary-${PERSON_A}"`))
    assert.match(html, /<select id="tax-field-employer-reimbursement-r1"/)
    assert.match(html, /<select id="tax-field-employer-reimbursement-r2"/)
    assert.match(html, /<button id="tax-field-employer-reimbursement-expense-r1"/)
    assert.doesNotMatch(html, /tax-field-employer-reimbursement-expense-r2/, 'no expense link, no unlink action')
    assert.equal(anchors.fieldAnchor('net-taxable-salary', PERSON_A), `tax-field-net-taxable-salary-${PERSON_A}`)
    const content = text(html)
    assert.ok(content.includes('Aucun salaire net imposable saisi pour 2026'))
    assert.ok(content.includes('La dépense liée e9 est introuvable pour 2026'))
    assert.ok(content.includes('Non indiqué'))
  })

  test('the reintegration indicator shows the saved answer', async () => {
    const html = await render(Editor, { inputs, personId: PERSON_A, year: 2026 })
    const selectTag = id => html.slice(html.indexOf(`<select id="${id}"`)).split('>')[0]
    assert.match(selectTag('tax-field-employer-reimbursement-r2'), /value="yes"/)
    assert.match(selectTag('tax-field-employer-reimbursement-r1'), /value=""/, 'unknown indicator stays "Non indiqué"')
  })

  test('a route hash becomes a field id only for field links', () => {
    assert.equal(anchors.fieldIdFromHash('#tax-field-employer-reimbursement-r1'), 'tax-field-employer-reimbursement-r1')
    assert.equal(anchors.fieldIdFromHash('#top'), null)
    assert.equal(anchors.fieldIdFromHash(''), null)
  })

  test('focusField waits for the field to be rendered, then focuses it', async () => {
    let calls = 0
    const focused = []
    const element = { scrollIntoView: () => {}, focus: () => focused.push('field') }
    const root = { getElementById: id => (++calls >= 3 && id === 'tax-field-x' ? element : null) }
    assert.equal(await anchors.focusField('tax-field-x', 5, 1, root), true)
    assert.deepEqual(focused, ['field'])
    assert.equal(await anchors.focusField('tax-field-missing', 2, 1, { getElementById: () => null }), false)
  })
})

describe('translations', () => {
  test('every warning code produced by the API is translated', () => {
    const codes = ['negative_travel_allowance', 'other_year_configuration_ignored', 'travel_allowance_without_trip', 'meal_contribution_not_counted_twice', 'reimbursements_not_reintegrated', 'forfait_exceeds_salary', 'unknown']
    for (const code of codes) assert.equal(typeof fr.tax.comparison.warning[code], 'string', code)
  })
})

describe('year closing', () => {
  test('an open available year offers the explicit "validate and close" flow, not a closed state', async () => {
    const html = await render(Result, { comparison: comparison(), baseId: 'c' })
    const content = text(html)
    assert.match(html, /data-testid="tax-closure-close"/)
    assert.ok(content.includes(fr.tax.closure.validateAndClose))
    assert.doesNotMatch(html, /data-testid="tax-closure-badge"/)
    assert.doesNotMatch(html, /data-testid="tax-closure-reopen"/)
    assert.ok(content.includes('Estimation provisoire'))
  })

  test('incomplete and configuration_missing results are unchanged and cannot be closed', async () => {
    for (const status of ['incomplete', 'configuration_missing']) {
      const html = await render(Result, { comparison: comparison({ status, display: { missingFields: [] } }), baseId: 'c' })
      assert.match(html, /data-testid="tax-missing"/, status)
      assert.doesNotMatch(html, /data-testid="tax-closure-close"/, status)
      assert.match(html, /data-testid="tax-closure-not-closable"/, status)
      assert.doesNotMatch(html, /data-testid="tax-closure-badge"/, status)
    }
  })

  test('a closed year shows the snapshot amounts, the closed badge and date, and is not presented as the final tax', async () => {
    const html = await render(Result, { comparison: comparison({ status: 'closed', display: { temporalQualification: 'closed_estimate' } }), baseId: 'c' })
    const content = text(html)
    assert.match(html, /data-testid="tax-banner"/)
    assert.match(html, /data-testid="tax-closure-badge"/)
    assert.ok(content.includes('Clôturée'))
    assert.ok(content.includes('10 février 2027'), content)
    assert.ok(content.includes('version 1'))
    assert.ok(content.includes('pas le calcul définitif de votre impôt'))
    assert.ok(content.includes('Estimation clôturée'))
    assert.match(html, /data-testid="tax-closure-unchanged"/)
    assert.match(html, /data-testid="tax-closure-reopen"/)
    assert.doesNotMatch(html, /data-testid="tax-closure-close"/)
    assert.ok(content.includes('Version 1 : clôturée le 10 février 2027 (en vigueur)'))
    assert.match(html, /data-testid="tax-disclaimer"/)
  })

  test('a modification after closing is visible: the snapshot is kept and the current difference is shown', async () => {
    const html = await render(Result, {
      comparison: comparison({
        status: 'closed',
        difference: 100_000,
        closure: {
          afterClosure: { policy: 'snapshot_kept_reopen_to_recalculate', dataChanged: true, currentStatus: 'available', currentDifferenceCents: 43_000, currentRoundedDifferenceEuros: 430 },
          history: [
            { version: 1, state: 'reopened', closedAt: '2027-02-10T09:30:00+00:00', reopenedAt: '2027-03-01T08:00:00+00:00' },
            { version: 2, state: 'closed', closedAt: '2027-03-05T10:00:00+00:00', reopenedAt: null },
          ],
          version: 2,
        },
      }),
      baseId: 'c',
    })
    const content = text(html)
    assert.match(html, /data-testid="tax-closure-changed"/)
    assert.ok(content.includes(fr.tax.closure.changedAfterClosure))
    assert.ok(/430,00\s€/.test(content), content)
    assert.ok(content.includes(fr.tax.closure.reopenToRecalculate))
    assert.ok(content.includes('Version 1 : clôturée le 10 février 2027, rouverte le 1 mars 2027') || content.includes('Version 1 : clôturée le 10 février 2027, rouverte le 1er mars 2027'), content)
    assert.ok(content.includes('Version 2 : clôturée le 5 mars 2027 (en vigueur)'))
    // The snapshot (difference 1 000 €) is still the displayed result.
    assert.ok(/1\s000\s€/.test(text(html)), content)
  })

  test('a closed year whose current data became incomplete says the comparison is unavailable', async () => {
    const html = await render(Result, {
      comparison: comparison({ status: 'closed', closure: { afterClosure: { policy: 'snapshot_kept_reopen_to_recalculate', dataChanged: true, currentStatus: 'configuration_missing', currentDifferenceCents: null, currentRoundedDifferenceEuros: null } } }),
      baseId: 'c',
    })
    assert.ok(text(html).includes(fr.tax.closure.currentUnavailable))
  })

  test('closure wording never presents the result as a final tax calculation', () => {
    const messages = [...Object.values(fr.tax.closure), fr.tax.comparison.temporal.closed_estimate]
    for (const message of messages) {
      const normalized = message.toLowerCase()
      if (normalized.includes('définitif')) assert.match(normalized, /(pas|n’est pas|ne constitue pas)[^.]*définitif/, message)
    }
    assert.equal(utils.fmtClosureDate(null), '')
    assert.equal(utils.fmtClosureDate('not-a-date'), 'not-a-date')
  })
})
