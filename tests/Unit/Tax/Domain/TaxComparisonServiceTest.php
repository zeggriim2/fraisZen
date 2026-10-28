<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Domain;

use App\Admin\Domain\Entity\FiscalConfig;
use App\Expense\Domain\Entity\MealExpense;
use App\Expense\Domain\Entity\ParkingExpense;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\Tax\Domain\Entity\EmployerReimbursement;
use App\Tax\Domain\Entity\NetTaxableSalary;
use App\Tax\Domain\Service\TaxComparisonResult;
use App\Tax\Domain\Service\TaxComparisonService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TaxComparisonServiceTest extends TestCase
{
    private const MEAL_ID = '11111111-1111-4111-8111-111111111111';
    private const OTHER_MEAL_ID = '22222222-2222-4222-8222-222222222222';

    public function testConsolidatesEveryEmployerAndPeriodOfOnePersonAndYear(): void
    {
        $salaries = [
            $this->salary('s1', 'person-a', 'Employer A', '2026-01-01', '2026-06-30', 1_500_000),
            $this->salary('s2', 'person-a', 'Employer B', '2026-07-01', '2026-09-30', 800_000),
            $this->salary('s3', 'person-a', 'Employer B', '2026-10-01', '2026-12-31', 700_000),
            $this->salary('s4', 'person-b', 'Employer C', '2026-01-01', '2026-12-31', 9_999_999),
            $this->salary('s5', 'person-a', 'Employer A', '2025-01-01', '2025-12-31', 1_000_000),
        ];
        $expenses = [
            new ParkingExpense(ExpenseId::generate(), 'person-a', new \DateTimeImmutable('2026-03-01'), null, 1500.00),
            new MealExpense(ExpenseId::fromString(self::MEAL_ID), 'person-a', new \DateTimeImmutable('2026-05-01'), null, 300.00, 5.00, 10.00),
            new ParkingExpense(ExpenseId::generate(), 'person-a', new \DateTimeImmutable('2025-12-31'), null, 999.00),
            new ParkingExpense(ExpenseId::generate(), 'person-b', new \DateTimeImmutable('2026-03-01'), null, 5000.00),
        ];
        $reimbursements = [
            new EmployerReimbursement('r1', 'person-a', 'Employer A', 2026, 10_000, 'meal', true, new \DateTimeImmutable('2026-05-01'), self::MEAL_ID),
            new EmployerReimbursement('r2', 'person-a', 'Employer A', 2026, 90_000, 'transport', false),
            new EmployerReimbursement('r3', 'person-a', 'Employer B', 2026, 20_000, 'transport', true),
            new EmployerReimbursement('r4', 'person-b', 'Employer C', 2026, 77_700, 'transport', true),
            new EmployerReimbursement('r5', 'person-a', 'Employer A', 2025, 55_500, 'transport', true),
        ];

        $result = (new TaxComparisonService())->compare('person-a', 2026, $salaries, $reimbursements, $expenses, $this->config(2026, 10, 50_000, 1_400_000))->toArray();

        self::assertSame('available', $result['status']);
        self::assertSame('person-a', $result['personId']);
        self::assertSame(2026, $result['year']);
        self::assertSame([
            'netTaxableSalaryCents' => 3_000_000,
            'forfaitRatePercent' => 10,
            'theoreticalForfaitCents' => 300_000,
            'forfaitMinimumCents' => 50_000,
            'forfaitMaximumCents' => 1_400_000,
            'forfaitLimitApplied' => 'none',
            'forfaitCents' => 300_000,
            'realExpensesCents' => 178_500,
            'reintegratedReimbursementsCents' => 29_000,
            'excludedAlreadyDeductedContributionCents' => 1_000,
            'baseForfaitCents' => 2_700_000,
            'baseRealExpensesCents' => 2_850_500,
            'differenceCents' => -150_500,
        ], $result['amounts']);
        self::assertSame('less_advantageous', $result['decision']);
        self::assertSame([], $result['missing']);
        self::assertSame(['year' => 2026, 'version' => '2026.1', 'source' => 'BOFiP-IR-BASE-2026', 'effectiveFrom' => '2026-01-01'], $result['configuration']);
        self::assertSame([
            'net_taxable_salary_consolidation',
            'forfait_rate_percent',
            'forfait_minimum_cents',
            'forfait_maximum_cents',
            'base_forfait_salary_minus_forfait',
            'base_real_expenses_salary_plus_reintegrated_minus_expenses',
            'reintegration_explicit_only',
            'meal_employer_contribution_not_counted_twice',
        ], array_column($result['rules'], 'code'));
        self::assertSame(['code' => 'forfait_rate_percent', 'value' => 10, 'version' => '2026.1', 'source' => 'BOFiP-IR-BASE-2026', 'effectiveFrom' => '2026-01-01'], $result['rules'][1]);
        self::assertSame([
            'Reimbursement r1: 1000 cents already deducted as employer contribution of meal expense '.self::MEAL_ID.' were not counted twice.',
            '1 reimbursement(s) not marked for reintegration were not added to the real expenses base.',
        ], $result['warnings']);
    }

    public function testAppliesMinimum(): void
    {
        $amounts = $this->amounts(300_000, $this->config(2026, 10, 50_000, 1_400_000));

        self::assertSame(30_000, $amounts['theoreticalForfaitCents']);
        self::assertSame('minimum', $amounts['forfaitLimitApplied']);
        self::assertSame(50_000, $amounts['forfaitCents']);
        self::assertSame(250_000, $amounts['baseForfaitCents']);
    }

    public function testAppliesMaximum(): void
    {
        $amounts = $this->amounts(20_000_000, $this->config(2026, 10, 50_000, 1_400_000));

        self::assertSame(2_000_000, $amounts['theoreticalForfaitCents']);
        self::assertSame('maximum', $amounts['forfaitLimitApplied']);
        self::assertSame(1_400_000, $amounts['forfaitCents']);
        self::assertSame(18_600_000, $amounts['baseForfaitCents']);
    }

    /** @return iterable<string, array{int, int, string, int}> */
    public static function boundaryProvider(): iterable
    {
        yield 'exactly the minimum' => [500_000, 50_000, 'none', 50_000];
        yield 'one cent below the minimum' => [499_999, 49_999, 'minimum', 50_000];
        yield 'exactly the maximum' => [14_000_000, 1_400_000, 'none', 1_400_000];
        yield 'one cent above the maximum' => [14_000_010, 1_400_001, 'maximum', 1_400_000];
        yield 'theoretical forfait floored to the cent' => [1_000_009, 100_000, 'none', 100_000];
        yield 'zero salary' => [0, 0, 'minimum', 50_000];
    }

    #[DataProvider('boundaryProvider')]
    public function testBoundaryValues(int $salary, int $theoretical, string $limit, int $forfait): void
    {
        $amounts = $this->amounts($salary, $this->config(2026, 10, 50_000, 1_400_000));

        self::assertSame($theoretical, $amounts['theoreticalForfaitCents']);
        self::assertSame($limit, $amounts['forfaitLimitApplied']);
        self::assertSame($forfait, $amounts['forfaitCents']);
        self::assertSame($salary - $forfait, $amounts['baseForfaitCents']);
    }

    /** @return iterable<string, array{float, int, string}> */
    public static function decisionProvider(): iterable
    {
        yield 'equality' => [1000.00, 0, 'equivalent'];
        yield 'one cent more real expenses' => [1000.01, 1, 'advantageous'];
        yield 'one cent less real expenses (negative difference)' => [999.99, -1, 'less_advantageous'];
    }

    #[DataProvider('decisionProvider')]
    public function testDecisionUsesTheExactSignOfTheDifference(float $parking, int $difference, string $decision): void
    {
        $result = (new TaxComparisonService())->compare(
            'person-a',
            2026,
            [$this->salary('s1', 'person-a', 'Employer A', '2026-01-01', '2026-12-31', 1_000_000)],
            [],
            [new ParkingExpense(ExpenseId::generate(), 'person-a', new \DateTimeImmutable('2026-02-01'), null, $parking)],
            $this->config(2026, 10, 0, 1_400_000),
        );

        self::assertSame(100_000, $result->amounts()['forfaitCents'] ?? null);
        self::assertSame($difference, $result->amounts()['differenceCents'] ?? null);
        self::assertSame($decision, $result->decision());
        self::assertSame(TaxComparisonResult::STATUS_AVAILABLE, $result->status());
    }

    public function testOnlyExplicitlyReintegratedReimbursementsAreAddedAndRealExpensesStayIndependent(): void
    {
        $salary = [$this->salary('s1', 'person-a', 'Employer A', '2026-01-01', '2026-12-31', 1_000_000)];
        $expenses = [new ParkingExpense(ExpenseId::generate(), 'person-a', new \DateTimeImmutable('2026-02-01'), null, 400.00)];
        $config = $this->config(2026, 10, 0, 1_400_000);
        $service = new TaxComparisonService();

        $without = $service->compare('person-a', 2026, $salary, [], $expenses, $config)->amounts();
        $with = $service->compare('person-a', 2026, $salary, [
            new EmployerReimbursement('r1', 'person-a', 'Employer A', 2026, 12_345, 'transport', true),
            new EmployerReimbursement('r2', 'person-a', 'Employer A', 2026, 50_000, 'transport', false),
        ], $expenses, $config)->amounts();

        self::assertNotNull($without);
        self::assertNotNull($with);
        self::assertSame(40_000, $without['realExpensesCents']);
        self::assertSame(40_000, $with['realExpensesCents']);
        self::assertSame(0, $without['reintegratedReimbursementsCents']);
        self::assertSame(12_345, $with['reintegratedReimbursementsCents']);
        self::assertSame(1_000_000 - 40_000, $without['baseRealExpensesCents']);
        self::assertSame(1_000_000 + 12_345 - 40_000, $with['baseRealExpensesCents']);
    }

    public function testDoesNotCountLinkedMealContributionTwiceAndKeepsPeopleIsolated(): void
    {
        $salaries = [
            $this->salary('s1', 'person-a', 'Employer A', '2026-01-01', '2026-12-31', 100_000),
            $this->salary('s2', 'person-b', 'Employer B', '2026-01-01', '2026-12-31', 900_000),
        ];
        // Deductible meal: 20.00 - 5.00 (home meal) - 6.00 (employer contribution) = 9.00.
        $meal = new MealExpense(ExpenseId::fromString(self::MEAL_ID), 'person-a', new \DateTimeImmutable('2026-05-01'), null, 20.00, 5.00, 6.00);
        $reimbursements = [
            new EmployerReimbursement('r1', 'person-a', 'Employer A', 2026, 1_000, 'meal', true, new \DateTimeImmutable('2026-05-01'), self::MEAL_ID),
            // Same meal again: the 6.00 contribution is already fully consumed by r1.
            new EmployerReimbursement('r2', 'person-a', 'Employer A', 2026, 300, 'meal', true, new \DateTimeImmutable('2026-05-02'), self::MEAL_ID),
            // Not linked to any expense: nothing can be deduplicated.
            new EmployerReimbursement('r3', 'person-a', 'Employer A', 2026, 600, 'meal', true),
        ];

        $result = (new TaxComparisonService())->compare('person-a', 2026, $salaries, $reimbursements, [$meal], $this->config(2026, 10, 0, 1_400_000));
        $amounts = $result->amounts();

        self::assertNotNull($amounts);
        self::assertSame(100_000, $amounts['netTaxableSalaryCents']);
        self::assertSame(900, $amounts['realExpensesCents']);
        self::assertSame(600, $amounts['excludedAlreadyDeductedContributionCents']);
        self::assertSame(400 + 300 + 600, $amounts['reintegratedReimbursementsCents']);
        self::assertSame(100_000 + 1_300 - 900, $amounts['baseRealExpensesCents']);
        self::assertSame(['Reimbursement r1: 600 cents already deducted as employer contribution of meal expense '.self::MEAL_ID.' were not counted twice.'], $result->warnings());
    }

    public function testMealWithoutReceiptDoesNotDeductTheContributionSoNothingIsExcluded(): void
    {
        $meal = new MealExpense(ExpenseId::fromString(self::MEAL_ID), 'person-a', new \DateTimeImmutable('2026-05-01'), null, 20.00, 5.35, 6.00, true);
        $result = (new TaxComparisonService())->compare(
            'person-a',
            2026,
            [$this->salary('s1', 'person-a', 'Employer A', '2026-01-01', '2026-12-31', 100_000)],
            [new EmployerReimbursement('r1', 'person-a', 'Employer A', 2026, 600, 'meal', true, null, self::MEAL_ID)],
            [$meal],
            $this->config(2026, 10, 0, 1_400_000),
        );

        self::assertSame(535, $result->amounts()['realExpensesCents'] ?? null);
        self::assertSame(600, $result->amounts()['reintegratedReimbursementsCents'] ?? null);
        self::assertSame(0, $result->amounts()['excludedAlreadyDeductedContributionCents'] ?? null);
    }

    public function testMissingConfigurationIsReportedWithoutFallback(): void
    {
        $service = new TaxComparisonService();
        $salary = [$this->salary('s1', 'person-a', 'Employer A', '2026-01-01', '2026-12-31', 100_000)];

        $none = $service->compare('person-a', 2026, $salary, [], [], null)->toArray();
        self::assertSame('configuration_missing', $none['status']);
        self::assertSame(['fiscal_config[2026]'], $none['missing']);
        self::assertNull($none['amounts']);
        self::assertNull($none['decision']);
        self::assertNull($none['configuration']);
        self::assertSame([], $none['rules']);

        $noData = $service->compare('person-a', 2026, [], [], [], null);
        self::assertSame(['fiscal_config[2026]', 'net_taxable_salary[2026]'], $noData->missing());
    }

    public function testConfigurationForAnotherYearIsMissing(): void
    {
        $salary = [$this->salary('s1', 'person-a', 'Employer A', '2026-01-01', '2026-12-31', 100_000)];
        $result = (new TaxComparisonService())->compare('person-a', 2026, $salary, [], [], $this->config(2025, 10, 0, 1_400_000))->toArray();

        self::assertSame('configuration_missing', $result['status']);
        self::assertSame(['fiscal_config[2026]'], $result['missing']);
        self::assertNull($result['configuration']);
        self::assertNull($result['amounts']);
        self::assertSame(['The fiscal configuration of year 2025 was ignored: only the configuration of year 2026 can be used.'], $result['warnings']);
    }

    public function testIncompleteConfigurationListsExactlyTheMissingParameters(): void
    {
        $legacy = new FiscalConfig(2026, '2.70', '5.35');
        $partial = new FiscalConfig(2026, '2.70', '5.35', 10, null, 1_400_000, 'v1', null, new \DateTimeImmutable('2026-01-01'));
        $salary = [$this->salary('s1', 'person-a', 'Employer A', '2026-01-01', '2026-12-31', 100_000)];
        $service = new TaxComparisonService();

        self::assertSame([
            'fiscal_config[2026].forfait_rate_percent',
            'fiscal_config[2026].forfait_minimum_cents',
            'fiscal_config[2026].forfait_maximum_cents',
            'fiscal_config[2026].version',
            'fiscal_config[2026].source',
            'fiscal_config[2026].effective_from',
        ], $service->compare('person-a', 2026, $salary, [], [], $legacy)->missing());

        $result = $service->compare('person-a', 2026, $salary, [], [], $partial);
        self::assertSame('configuration_missing', $result->status());
        self::assertSame(['fiscal_config[2026].forfait_minimum_cents', 'fiscal_config[2026].source'], $result->missing());
        self::assertSame(['year' => 2026, 'version' => 'v1', 'source' => null, 'effectiveFrom' => '2026-01-01'], $result->configuration());
    }

    public function testMissingSalaryOrReimbursementInformationIsIncomplete(): void
    {
        $service = new TaxComparisonService();
        $config = $this->config(2026, 10, 0, 1_400_000);

        $noSalary = $service->compare('person-a', 2026, [
            $this->salary('s1', 'person-a', 'Employer A', '2025-01-01', '2025-12-31', 100_000),
            $this->salary('s2', 'person-b', 'Employer A', '2026-01-01', '2026-12-31', 100_000),
        ], [], [], $config)->toArray();
        self::assertSame('incomplete', $noSalary['status']);
        self::assertSame(['net_taxable_salary[2026]'], $noSalary['missing']);
        self::assertNull($noSalary['amounts']);
        self::assertSame(['year' => 2026, 'version' => '2026.1', 'source' => 'BOFiP-IR-BASE-2026', 'effectiveFrom' => '2026-01-01'], $noSalary['configuration']);

        $otherPersonMeal = new MealExpense(ExpenseId::fromString(self::OTHER_MEAL_ID), 'person-b', new \DateTimeImmutable('2026-05-01'), null, 20.00, 5.00, 6.00);
        $result = $service->compare('person-a', 2026, [$this->salary('s1', 'person-a', 'Employer A', '2026-01-01', '2026-12-31', 100_000)], [
            new EmployerReimbursement('r1', 'person-a', 'Employer A', 2026, 1_000, 'meal', null),
            new EmployerReimbursement('r2', 'person-a', 'Employer A', 2026, 600, 'meal', true, null, self::OTHER_MEAL_ID),
            new EmployerReimbursement('r3', 'person-a', 'Employer A', 2026, 600, 'meal', false, null, self::MEAL_ID),
        ], [$otherPersonMeal], $config);

        self::assertSame('incomplete', $result->status());
        self::assertSame([
            'employer_reimbursement[r1].to_reintegrate',
            'employer_reimbursement[r2].expense['.self::OTHER_MEAL_ID.']',
        ], $result->missing());
        self::assertNull($result->decision());
    }

    public function testAResultOfOnePersonIsNeverUsedForAnother(): void
    {
        $salaries = [
            $this->salary('s1', 'person-a', 'Employer A', '2026-01-01', '2026-12-31', 1_000_000),
            $this->salary('s2', 'person-b', 'Employer B', '2026-01-01', '2026-12-31', 2_000_000),
        ];
        $reimbursements = [new EmployerReimbursement('r1', 'person-b', 'Employer B', 2026, 7_000, 'transport', true)];
        $expenses = [new ParkingExpense(ExpenseId::generate(), 'person-a', new \DateTimeImmutable('2026-02-01'), null, 300.00)];
        $config = $this->config(2026, 10, 0, 1_400_000);
        $service = new TaxComparisonService();

        $a = $service->compare('person-a', 2026, $salaries, $reimbursements, $expenses, $config);
        $b = $service->compare('person-b', 2026, $salaries, $reimbursements, $expenses, $config);
        $unknown = $service->compare('person-c', 2026, $salaries, $reimbursements, $expenses, $config);

        self::assertSame('person-a', $a->personId());
        self::assertSame([1_000_000, 30_000, 0], [$a->amounts()['netTaxableSalaryCents'] ?? null, $a->amounts()['realExpensesCents'] ?? null, $a->amounts()['reintegratedReimbursementsCents'] ?? null]);
        self::assertSame('person-b', $b->personId());
        self::assertSame([2_000_000, 0, 7_000], [$b->amounts()['netTaxableSalaryCents'] ?? null, $b->amounts()['realExpensesCents'] ?? null, $b->amounts()['reintegratedReimbursementsCents'] ?? null]);
        self::assertSame('incomplete', $unknown->status());
        self::assertSame(['net_taxable_salary[2026]'], $unknown->missing());
    }

    public function testEntitiesRejectInvalidData(): void
    {
        $cases = [
            fn () => $this->salary('s1', 'person-a', 'Employer A', '2026-01-01', '2026-12-31', -1),
            fn () => $this->salary('s1', 'person-a', 'Employer A', '2025-12-31', '2026-12-31', 1),
            fn () => $this->salary('s1', 'person-a', 'Employer A', '2026-07-01', '2026-06-30', 1),
            fn () => $this->salary('s1', 'person-a', ' ', '2026-01-01', '2026-12-31', 1),
            fn () => new EmployerReimbursement('r1', 'person-a', 'Employer A', 2026, -1, 'meal', true),
            fn () => new EmployerReimbursement('r1', 'person-a', '', 2026, 1, 'meal', true),
            fn () => new EmployerReimbursement('r1', 'person-a', 'Employer A', 2026, 1, '', true),
            fn () => new EmployerReimbursement('r1', 'person-a', 'Employer A', 2026, 1, 'meal', true, new \DateTimeImmutable('2025-12-31')),
            fn () => new EmployerReimbursement('r1', 'person-a', 'Employer A', 2026, 1, 'meal', true, null, ' '),
            fn () => new FiscalConfig(2026, '2.70', '5.35', 0),
            fn () => new FiscalConfig(2026, '2.70', '5.35', 10, 2, 1),
            fn () => new FiscalConfig(2026, '2.70', '5.35', 10, -1),
            fn () => new FiscalConfig(2026, '2.70', '5.35', 10, 0, 1, ' '),
        ];
        foreach ($cases as $index => $case) {
            try {
                $case();
                self::fail(sprintf('Case %d should have been rejected.', $index));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testLegacyFiscalConfigCanReceiveVersionedComparisonRules(): void
    {
        $config = new FiscalConfig(2026, '2.70', '5.35');
        self::assertFalse($config->isComplete());
        self::assertSame(2.70, $config->remoteWorkDailyAllowance());
        self::assertSame(5.35, $config->homeMealValue());

        $config->defineTaxComparisonRules(10, 50_000, 1_400_000, '2026.1', 'BOFiP', new \DateTimeImmutable('2026-01-01'));

        self::assertTrue($config->isComplete());
        self::assertSame([], $config->missingTaxComparisonFields());
        self::assertSame(['year' => 2026, 'version' => '2026.1', 'source' => 'BOFiP', 'effectiveFrom' => '2026-01-01'], $config->metadata());
    }

    /** @return array<string, mixed> */
    private function amounts(int $salaryCents, FiscalConfig $config): array
    {
        $result = (new TaxComparisonService())->compare('person-a', 2026, [$this->salary('s1', 'person-a', 'Employer A', '2026-01-01', '2026-12-31', $salaryCents)], [], [], $config);
        self::assertSame('available', $result->status());
        $amounts = $result->amounts();
        self::assertNotNull($amounts);

        return $amounts;
    }

    private function salary(string $id, string $personId, string $employer, string $start, string $end, int $cents): NetTaxableSalary
    {
        return new NetTaxableSalary($id, $personId, $employer, (int) substr($start, 0, 4), new \DateTimeImmutable($start), new \DateTimeImmutable($end), $cents);
    }

    private function config(int $year, int $rate, int $minimum, int $maximum): FiscalConfig
    {
        return new FiscalConfig($year, '2.70', '5.35', $rate, $minimum, $maximum, $year.'.1', 'BOFiP-IR-BASE-'.$year, new \DateTimeImmutable($year.'-01-01'));
    }
}
