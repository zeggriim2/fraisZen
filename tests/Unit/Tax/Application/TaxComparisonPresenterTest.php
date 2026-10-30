<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Application;

use App\Tax\Application\Presenter\TaxComparisonPresenter;
use App\Tax\Domain\Service\TaxComparisonResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaxComparisonPresenterTest extends TestCase
{
    private const ORIGINS = [
        'netTaxableSalary' => ['entries' => 1, 'employers' => ['ACME']],
        'realExpenses' => ['entries' => 2, 'trips' => 0, 'travelAllowanceCents' => null],
        'reimbursements' => ['entries' => 0, 'reintegrated' => 0],
    ];

    /** @return iterable<string, array{int, bool, string, int, bool}> */
    public static function differences(): iterable
    {
        // differenceCents => showBanner, outcome, rounded euros, below one euro
        yield 'positive' => [123_456, true, 'advantageous', 1_235, false];
        yield 'positive half rounds up' => [150, true, 'advantageous', 2, false];
        yield 'positive just below half' => [149, true, 'advantageous', 1, false];
        yield 'positive exactly one euro' => [100, true, 'advantageous', 1, false];
        yield 'positive below one euro' => [99, true, 'advantageous', 1, true];
        yield 'positive one cent' => [1, true, 'advantageous', 0, true];
        yield 'zero' => [0, false, 'equivalent', 0, false];
        yield 'negative one cent' => [-1, false, 'less_advantageous', 0, true];
        yield 'negative below one euro' => [-60, false, 'less_advantageous', -1, true];
        yield 'negative half rounds away from zero' => [-150, false, 'less_advantageous', -2, false];
        yield 'negative' => [-98_765, false, 'less_advantageous', -988, false];
    }

    #[DataProvider('differences')]
    public function testBannerIsShownOnlyForAStrictlyPositiveExactDifference(int $difference, bool $banner, string $outcome, int $rounded, bool $belowOne): void
    {
        $presented = (new TaxComparisonPresenter())->present($this->available($difference), new \DateTimeImmutable('2026-10-30'), self::ORIGINS);

        self::assertSame($banner, $presented['display']['showBanner']);
        self::assertSame($outcome, $presented['display']['outcome']);
        self::assertSame($rounded, $presented['display']['roundedDifferenceEuros']);
        self::assertSame($belowOne, $presented['display']['differenceBelowOneEuro']);
        self::assertSame($difference, $presented['amounts']['differenceCents'] ?? null, 'the exact cents stay available for the detail');
        self::assertSame([], $presented['display']['missingFields']);
        self::assertSame('tax.comparison.disclaimer', $presented['display']['disclaimerKey']);
        self::assertSame(self::ORIGINS, $presented['origins']);
    }

    public function testIncompleteResultNeverShowsABannerAndListsEveryMissingFieldWithADirectLink(): void
    {
        $result = TaxComparisonResult::incomplete('person-a', 2025, [
            'net_taxable_salary[2025]',
            'employer_reimbursement[r1].to_reintegrate',
            'employer_reimbursement[r2].expense[e9]',
            'travel_allowance[2025]',
        ], ['year' => 2025, 'version' => '2025.1', 'source' => 'BOFiP', 'effectiveFrom' => '2025-01-01'], []);

        $display = (new TaxComparisonPresenter())->present($result, new \DateTimeImmutable('2026-10-30'), self::ORIGINS)['display'];

        self::assertFalse($display['showBanner']);
        self::assertNull($display['outcome']);
        self::assertNull($display['roundedDifferenceEuros']);
        self::assertFalse($display['differenceBelowOneEuro']);
        $personQuery = ['personId' => 'person-a', 'year' => '2025'];
        self::assertSame([
            [
                'code' => 'net_taxable_salary[2025]',
                'field' => 'net_taxable_salary',
                'params' => [],
                'link' => ['path' => '/persons', 'query' => $personQuery, 'hash' => '#tax-field-net-taxable-salary-person-a'],
            ],
            [
                'code' => 'employer_reimbursement[r1].to_reintegrate',
                'field' => 'employer_reimbursement_to_reintegrate',
                'params' => ['reimbursementId' => 'r1'],
                'link' => ['path' => '/persons', 'query' => $personQuery, 'hash' => '#tax-field-employer-reimbursement-r1'],
            ],
            [
                'code' => 'employer_reimbursement[r2].expense[e9]',
                'field' => 'employer_reimbursement_expense',
                'params' => ['reimbursementId' => 'r2', 'expenseId' => 'e9'],
                'link' => ['path' => '/persons', 'query' => $personQuery, 'hash' => '#tax-field-employer-reimbursement-expense-r2'],
            ],
            [
                'code' => 'travel_allowance[2025]',
                'field' => 'travel_allowance',
                'params' => [],
                'link' => ['path' => '/trips', 'query' => $personQuery, 'hash' => '#tax-field-travel-allowance'],
            ],
        ], $display['missingFields']);
    }

    public function testConfigurationMissingNamesEveryMissingParameterWithoutALinkToANonexistentField(): void
    {
        $result = TaxComparisonResult::configurationMissing('person-a', 2026, [
            'fiscal_config[2026]',
            'fiscal_config[2026].forfait_rate_percent',
            'fiscal_config[2026].forfait_limits',
            'something_else',
        ], null, []);

        $presented = (new TaxComparisonPresenter())->present($result, new \DateTimeImmutable('2026-03-01'), self::ORIGINS);

        self::assertSame('configuration_missing', $presented['status']);
        self::assertFalse($presented['display']['showBanner']);
        self::assertSame(
            ['fiscal_config', 'fiscal_config_forfait_rate_percent', 'fiscal_config_forfait_limits', 'unknown'],
            array_column($presented['display']['missingFields'], 'field'),
        );
        // No screen holds the forfait parameters: no link rather than a link leading to no field.
        self::assertSame([null, null, null, null], array_column($presented['display']['missingFields'], 'link'));
    }

    /** @return iterable<string, array{string, string, array<string, string>}> */
    public static function engineWarnings(): iterable
    {
        yield 'negative allowance' => ['The provided kilometric allowance is negative and was rejected.', 'negative_travel_allowance', []];
        yield 'other year configuration' => ['The fiscal configuration of year 2025 was ignored: only the configuration of year 2026 can be used.', 'other_year_configuration_ignored', ['configurationYear' => '2025', 'year' => '2026']];
        yield 'allowance without trip' => ['A kilometric allowance of 12345 cents was provided although no travel expense exists for year 2026.', 'travel_allowance_without_trip', ['amountCents' => '12345', 'year' => '2026']];
        yield 'meal contribution' => ['Reimbursement r-1: 500 cents already deducted as employer contribution of meal expense e-1 were not counted twice.', 'meal_contribution_not_counted_twice', ['reimbursementId' => 'r-1', 'amountCents' => '500', 'expenseId' => 'e-1']];
        yield 'not reintegrated' => ['2 reimbursement(s) not marked for reintegration were not added to the real expenses base.', 'reimbursements_not_reintegrated', ['count' => '2']];
        yield 'forfait above salary' => ['The forfait minimum exceeds the net taxable salary: the forfait base is negative.', 'forfait_exceeds_salary', []];
        yield 'unknown sentence' => ['Something new.', 'unknown', []];
    }

    /** @param array<string, string> $params */
    #[DataProvider('engineWarnings')]
    public function testEngineWarningsBecomeTranslationCodes(string $warning, string $code, array $params): void
    {
        self::assertSame(['code' => $code, 'params' => $params], TaxComparisonPresenter::warning($warning));
    }

    public function testPresentedWarningsAreCodesAndNeverRawEngineText(): void
    {
        $result = $this->available(100, [
            '1 reimbursement(s) not marked for reintegration were not added to the real expenses base.',
            'The forfait minimum exceeds the net taxable salary: the forfait base is negative.',
        ]);

        $display = (new TaxComparisonPresenter())->present($result, new \DateTimeImmutable('2026-10-30'), self::ORIGINS)['display'];

        self::assertSame([
            ['code' => 'reimbursements_not_reintegrated', 'params' => ['count' => '1']],
            ['code' => 'forfait_exceeds_salary', 'params' => []],
        ], $display['warnings']);
    }

    /** @return iterable<string, array{int, string, string}> */
    public static function temporalCases(): iterable
    {
        yield 'current calendar year' => [2026, '2026-10-30', 'provisional_estimate'];
        yield 'first day of the current year' => [2026, '2026-01-01', 'provisional_estimate'];
        yield 'future year' => [2027, '2026-10-30', 'provisional_estimate'];
        yield 'elapsed year, not closed' => [2025, '2026-01-01', 'annual_estimate_to_verify'];
        yield 'older elapsed year' => [2021, '2026-10-30', 'annual_estimate_to_verify'];
    }

    #[DataProvider('temporalCases')]
    public function testQualifiesTheYearTemporally(int $year, string $today, string $expected): void
    {
        $result = TaxComparisonResult::configurationMissing('person-a', $year, [sprintf('fiscal_config[%d]', $year)], null, []);

        self::assertSame($expected, (new TaxComparisonPresenter())->present($result, new \DateTimeImmutable($today), self::ORIGINS)['display']['temporalQualification']);
    }

    /** @param list<string> $warnings */
    private function available(int $differenceCents, array $warnings = []): TaxComparisonResult
    {
        $baseReal = 2_000_000;

        return TaxComparisonResult::available('person-a', 2026, [
            'netTaxableSalaryCents' => 2_200_000,
            'forfaitRatePercent' => 10,
            'theoreticalForfaitCents' => 220_000,
            'forfaitMinimumCents' => 50_000,
            'forfaitMaximumCents' => 1_400_000,
            'forfaitLimitApplied' => 'none',
            'forfaitCents' => 220_000,
            'realExpensesCents' => 0,
            'reintegratedReimbursementsCents' => 0,
            'excludedAlreadyDeductedContributionCents' => 0,
            'baseForfaitCents' => $baseReal + $differenceCents,
            'baseRealExpensesCents' => $baseReal,
            'differenceCents' => $differenceCents,
        ], [], ['year' => 2026, 'version' => '2026.1', 'source' => 'BOFiP', 'effectiveFrom' => '2026-01-01'], $warnings);
    }
}
