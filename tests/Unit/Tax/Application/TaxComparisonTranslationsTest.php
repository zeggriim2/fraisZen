<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Application;

use App\Tax\Application\Presenter\TaxComparisonPresenter;
use App\Tax\Application\Service\TaxInputsService;
use PHPUnit\Framework\TestCase;

/**
 * Contract between the presenter keys and the frontend translation catalog
 * (frontend/src/i18n/locales/fr.json), and wording rules of the comparison UI.
 */
final class TaxComparisonTranslationsTest extends TestCase
{
    private const FRONTEND = __DIR__.'/../../../../frontend/src';

    /** @var array<string, string> */
    private array $messages;

    protected function setUp(): void
    {
        $json = file_get_contents(self::FRONTEND.'/i18n/locales/fr.json');
        self::assertIsString($json);
        $decoded = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        $this->messages = self::flatten($decoded);
    }

    public function testEveryKeyProducedByThePresenterIsTranslated(): void
    {
        $keys = [
            TaxComparisonPresenter::DISCLAIMER_KEY,
            'tax.comparison.temporal.'.TaxComparisonPresenter::TEMPORAL_PROVISIONAL,
            'tax.comparison.temporal.'.TaxComparisonPresenter::TEMPORAL_TO_VERIFY,
            'tax.comparison.temporal.'.TaxComparisonPresenter::TEMPORAL_CLOSED,
            'tax.comparison.outcome.equivalent',
            'tax.comparison.outcome.less_advantageous',
            'tax.comparison.status.configuration_missing',
            'tax.comparison.status.incomplete',
        ];
        $codes = [
            'net_taxable_salary[2026]', 'employer_reimbursement[r].to_reintegrate', 'employer_reimbursement[r].expense[e]',
            'travel_allowance[2026]', 'fiscal_config[2026]', 'fiscal_config[2026].forfait_limits', 'unknown_code',
        ];
        foreach (['forfait_rate_percent', 'forfait_minimum_cents', 'forfait_maximum_cents', 'version', 'source', 'effective_from'] as $field) {
            $codes[] = 'fiscal_config[2026].'.$field;
        }
        foreach ($codes as $code) {
            $keys[] = 'tax.comparison.missing.'.TaxComparisonPresenter::missingField($code, 'p', 2026)['field'];
        }
        foreach ([
            'net_taxable_salary_consolidation', 'forfait_rate_percent', 'forfait_minimum_cents', 'forfait_maximum_cents',
            'base_forfait_salary_minus_forfait', 'base_real_expenses_salary_plus_reintegrated_minus_expenses',
            'reintegration_explicit_only', 'meal_employer_contribution_not_counted_twice', 'travel_kilometric_allowance_cents',
        ] as $rule) {
            $keys[] = 'tax.comparison.detail.rule.'.$rule;
        }
        foreach (['none', 'minimum', 'maximum'] as $limit) {
            $keys[] = 'tax.comparison.detail.forfaitLimit.'.$limit;
        }
        foreach ([
            'The provided kilometric allowance is negative and was rejected.',
            'The fiscal configuration of year 2025 was ignored: only the configuration of year 2026 can be used.',
            'A kilometric allowance of 100 cents was provided although no travel expense exists for year 2026.',
            'Reimbursement r: 100 cents already deducted as employer contribution of meal expense e were not counted twice.',
            '1 reimbursement(s) not marked for reintegration were not added to the real expenses base.',
            'The forfait minimum exceeds the net taxable salary: the forfait base is negative.',
            'Unknown sentence.',
        ] as $warning) {
            $keys[] = 'tax.comparison.warning.'.TaxComparisonPresenter::warning($warning)['code'];
        }
        foreach (TaxInputsService::CATEGORIES as $category) {
            $keys[] = 'tax.inputs.categories.'.$category;
        }

        foreach ($keys as $key) {
            self::assertArrayHasKey($key, $this->messages, $key);
            self::assertNotSame('', trim($this->messages[$key]), $key);
        }
    }

    public function testEveryLiteralKeyUsedByTheComparisonComponentsExists(): void
    {
        $files = [
            self::FRONTEND.'/components/tax/TaxComparisonCard.vue',
            self::FRONTEND.'/components/tax/TaxComparisonDetail.vue',
            self::FRONTEND.'/components/tax/TaxComparisonResult.vue',
            self::FRONTEND.'/components/tax/TaxClosurePanel.vue',
            self::FRONTEND.'/components/tax/TaxInputsEditor.vue',
            self::FRONTEND.'/components/tax/TaxInputsPanel.vue',
            self::FRONTEND.'/utils/taxComparison.ts',
            self::FRONTEND.'/views/PersonsView.vue',
        ];
        $used = [];
        foreach ($files as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);
            preg_match_all("/\\bt\\('([a-zA-Z0-9_.]+)'/", $source, $m);
            $used = [...$used, ...$m[1]];
        }

        self::assertNotEmpty($used);
        foreach ($used as $key) {
            self::assertArrayHasKey($key, $this->messages, $key);
        }
    }

    public function testComponentsNeverDisplayRawEngineOrApiText(): void
    {
        foreach (['TaxComparisonCard.vue', 'TaxComparisonResult.vue', 'TaxComparisonDetail.vue', 'TaxClosurePanel.vue', 'TaxInputsEditor.vue', 'TaxInputsPanel.vue'] as $component) {
            $source = file_get_contents(self::FRONTEND.'/components/tax/'.$component);
            self::assertIsString($source);
            self::assertStringNotContainsString('lang="en"', $source, $component);
            self::assertStringNotContainsString('comparison.warnings', $source, $component.' must use display.warnings (translated codes)');
            self::assertStringNotContainsString('extractApiError', $source, $component.' must not show untranslated API messages');
        }
    }

    public function testBannerWordingPresentsAnEstimatedTaxableIncomeDifference(): void
    {
        self::assertSame('Écart estimé de revenu imposable : {amount}', $this->messages['tax.comparison.banner.amount']);
        self::assertSame('écart inférieur à 1 €', $this->messages['tax.comparison.banner.belowOneEuro']);
        self::assertSame('Comprendre le calcul', $this->messages['tax.comparison.banner.understand']);
        self::assertStringContainsString('même déduction', $this->messages['tax.comparison.outcome.equivalent']);
        self::assertStringContainsString('n’est pas recommandée', $this->messages['tax.comparison.outcome.less_advantageous']);
        self::assertStringContainsString('doit être complétée', $this->messages['tax.comparison.status.configuration_missing']);
        self::assertStringStartsWith('Estimation provisoire', $this->messages['tax.comparison.temporal.provisional_estimate']);
        self::assertStringStartsWith('Estimation annuelle à vérifier', $this->messages['tax.comparison.temporal.annual_estimate_to_verify']);

        foreach ($this->messages as $key => $message) {
            $normalized = mb_strtolower(str_replace('’', "'", $message));
            self::assertStringNotContainsString("économie d'impôt", $normalized, $key);
            self::assertStringNotContainsString('gain fiscal', $normalized, $key);
        }
    }

    public function testClosedResultIsNeverPresentedAsTheFinalTaxCalculation(): void
    {
        $closedEstimate = $this->messages['tax.comparison.temporal.'.TaxComparisonPresenter::TEMPORAL_CLOSED];
        self::assertStringStartsWith('Estimation clôturée', $closedEstimate);
        self::assertStringContainsString('ne constitue pas le calcul définitif de votre impôt', $closedEstimate);
        self::assertStringContainsString('Ce n’est pas le calcul définitif de votre impôt', $this->messages['tax.closure.closedNotice']);
        self::assertStringContainsString('{date}', $this->messages['tax.closure.closedNotice']);
        self::assertStringContainsString('{version}', $this->messages['tax.closure.closedNotice']);
        // The behaviour after closing is explicit: snapshot kept, difference shown, explicit reopening.
        self::assertStringContainsString('n’est pas modifié', $this->messages['tax.closure.changedAfterClosure']);
        self::assertStringContainsString('historique', $this->messages['tax.closure.changedAfterClosure']);
        self::assertStringContainsString('{amount}', $this->messages['tax.closure.currentDifference']);
        self::assertStringContainsString('Rouvrez', $this->messages['tax.closure.reopenToRecalculate']);

        foreach ($this->messages as $key => $message) {
            if (!str_starts_with($key, 'tax.')) {
                continue;
            }
            $normalized = mb_strtolower(str_replace('’', "'", $message));
            self::assertStringNotContainsString('impôt définitif', $normalized, $key);
            if (str_contains($normalized, 'définitif')) {
                self::assertMatchesRegularExpression("/(n'est pas|ne constitue pas|pas du) le calcul définitif|(n'est pas|ne constitue pas|pas du) calcul définitif/u", $normalized, $key);
            }
        }
    }

    public function testDisclaimerContent(): void
    {
        $disclaimer = $this->messages[TaxComparisonPresenter::DISCLAIMER_KEY];

        self::assertStringStartsWith('Avertissement fiscal', $disclaimer);
        foreach (['estimation indicative', 'revenu imposable', 'ne calcule pas votre impôt', 'ne constitue pas un conseil fiscal', 'informations saisies', 'impots.gouv.fr', 'responsable de votre déclaration'] as $mandatory) {
            self::assertStringContainsString($mandatory, $disclaimer);
        }
    }

    /**
     * @param array<array-key, mixed> $node
     *
     * @return array<string, string>
     */
    private static function flatten(array $node, string $prefix = ''): array
    {
        $flat = [];
        foreach ($node as $key => $value) {
            $path = '' === $prefix ? (string) $key : $prefix.'.'.$key;
            if (\is_array($value)) {
                $flat = [...$flat, ...self::flatten($value, $path)];
            } elseif (\is_string($value)) {
                $flat[$path] = $value;
            }
        }

        return $flat;
    }
}
