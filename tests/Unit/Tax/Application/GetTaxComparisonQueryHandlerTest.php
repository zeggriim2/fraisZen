<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Application;

use App\Admin\Domain\Entity\FiscalConfig;
use App\Admin\Domain\Repository\FiscalConfigRepositoryInterface;
use App\Expense\Domain\Entity\BaremeKilometrique;
use App\Expense\Domain\Entity\Expense;
use App\Expense\Domain\Entity\ParkingExpense;
use App\Expense\Domain\Entity\TravelExpense;
use App\Expense\Domain\Repository\BaremeKilometriqueRepositoryInterface;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\Service\KilometricAllowanceCalculator;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\Tax\Application\Query\GetTaxComparison\GetTaxComparisonQuery;
use App\Tax\Application\Query\GetTaxComparison\GetTaxComparisonQueryHandler;
use App\Tax\Domain\Entity\EmployerReimbursement;
use App\Tax\Domain\Entity\NetTaxableSalary;
use App\Tax\Domain\Repository\EmployerReimbursementRepositoryInterface;
use App\Tax\Domain\Repository\NetTaxableSalaryRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class GetTaxComparisonQueryHandlerTest extends TestCase
{
    private const TODAY = '2026-10-30';

    public function testEachPersonOfTheHouseholdGetsItsOwnIndependentResult(): void
    {
        // The fake repositories deliberately return the rows of EVERY person: nothing may leak.
        $handler = $this->handler(
            salaries: [
                $this->salary('s-a', 'person-a', 2026, 3_000_000),
                $this->salary('s-b', 'person-b', 2026, 2_000_000),
            ],
            expenses: [
                new ParkingExpense(ExpenseId::generate(), 'person-a', new \DateTimeImmutable('2026-02-01'), null, 1_000.00),
                new ParkingExpense(ExpenseId::generate(), 'person-b', new \DateTimeImmutable('2026-02-01'), null, 4_000.00),
            ],
            reimbursements: [
                new EmployerReimbursement('r-b', 'person-b', 'Beta', 2026, 10_000, 'transport', true),
            ],
            config: $this->config(2026),
        );

        $a = $handler(new GetTaxComparisonQuery('person-a', 2026, new \DateTimeImmutable(self::TODAY)));
        $b = $handler(new GetTaxComparisonQuery('person-b', 2026, new \DateTimeImmutable(self::TODAY)));

        self::assertSame('person-a', $a['personId']);
        self::assertSame(3_000_000, $a['amounts']['netTaxableSalaryCents'] ?? null);
        self::assertSame(100_000, $a['amounts']['realExpensesCents'] ?? null);
        self::assertSame(0, $a['amounts']['reintegratedReimbursementsCents'] ?? null);
        self::assertSame(-200_000, $a['amounts']['differenceCents'] ?? null);
        self::assertFalse($a['display']['showBanner'], 'forfait 300 000 > real expenses 100 000');
        self::assertSame(['entries' => 1, 'employers' => ['Employer person-a']], $a['origins']['netTaxableSalary']);
        self::assertSame(['entries' => 0, 'reintegrated' => 0], $a['origins']['reimbursements']);

        self::assertSame('person-b', $b['personId']);
        self::assertSame(2_000_000, $b['amounts']['netTaxableSalaryCents'] ?? null);
        self::assertSame(400_000, $b['amounts']['realExpensesCents'] ?? null);
        self::assertSame(10_000, $b['amounts']['reintegratedReimbursementsCents'] ?? null);
        self::assertSame(190_000, $b['amounts']['differenceCents'] ?? null);
        self::assertTrue($b['display']['showBanner']);
        self::assertSame(1_900, $b['display']['roundedDifferenceEuros']);
        self::assertSame(['entries' => 1, 'reintegrated' => 1], $b['origins']['reimbursements']);
    }

    public function testMissingSalaryAndReintegrationFlagMakeTheResultIncompleteWithLinks(): void
    {
        $handler = $this->handler(
            salaries: [$this->salary('s-b', 'person-b', 2025, 2_000_000)],
            expenses: [],
            reimbursements: [new EmployerReimbursement('r-a', 'person-a', 'Alpha', 2025, 5_000, 'transport', null)],
            config: $this->config(2025),
        );

        $result = $handler(new GetTaxComparisonQuery('person-a', 2025, new \DateTimeImmutable(self::TODAY)));

        self::assertSame('incomplete', $result['status']);
        self::assertNull($result['amounts']);
        self::assertSame(['net_taxable_salary[2025]', 'employer_reimbursement[r-a].to_reintegrate'], $result['missing']);
        self::assertSame(['net_taxable_salary', 'employer_reimbursement_to_reintegrate'], array_column($result['display']['missingFields'], 'field'));
        self::assertSame(['path' => '/persons', 'query' => ['personId' => 'person-a', 'year' => '2025'], 'hash' => '#tax-field-net-taxable-salary-person-a'], $result['display']['missingFields'][0]['link']);
        self::assertSame('#tax-field-employer-reimbursement-r-a', $result['display']['missingFields'][1]['link']['hash'] ?? null);
        self::assertFalse($result['display']['showBanner']);
        self::assertSame('annual_estimate_to_verify', $result['display']['temporalQualification']);
    }

    public function testAbsentConfigurationIsReportedAndAnotherYearIsNeverUsed(): void
    {
        $handler = $this->handler(
            salaries: [$this->salary('s-a', 'person-a', 2026, 3_000_000)],
            expenses: [],
            reimbursements: [],
            config: $this->config(2025),
        );

        $result = $handler(new GetTaxComparisonQuery('person-a', 2026, new \DateTimeImmutable(self::TODAY)));

        self::assertSame('configuration_missing', $result['status']);
        self::assertSame(['fiscal_config[2026]'], $result['missing']);
        self::assertSame('fiscal_config', $result['display']['missingFields'][0]['field']);
        self::assertNull($result['display']['missingFields'][0]['link'], 'no screen holds the forfait parameters');
        self::assertSame([['code' => 'other_year_configuration_ignored', 'params' => ['configurationYear' => '2025', 'year' => '2026']]], $result['display']['warnings']);
        self::assertFalse($result['display']['showBanner']);
        self::assertSame('provisional_estimate', $result['display']['temporalQualification']);
    }

    public function testTripsAreValuedWithTheKilometricScaleOfTheRequestedYear(): void
    {
        $trip = new TravelExpense(ExpenseId::generate(), 'person-a', new \DateTimeImmutable('2026-04-02'), null, 'A', 'B', 6_000.0, 5);
        $otherPersonTrip = new TravelExpense(ExpenseId::generate(), 'person-b', new \DateTimeImmutable('2026-04-02'), null, 'A', 'B', 9_000.0, 7);
        $handler = $this->handler(
            salaries: [$this->salary('s-a', 'person-a', 2026, 3_000_000)],
            expenses: [$trip, $otherPersonTrip],
            reimbursements: [],
            config: $this->config(2026),
        );
        $expected = (int) round($this->calculator()->calculateAnnualDeduction([
            ['distanceKm' => 6_000.0, 'vehiclePower' => 5, 'vehicleType' => 'car', 'isElectric' => false],
        ], 2026) * 100.0);

        $result = $handler(new GetTaxComparisonQuery('person-a', 2026, new \DateTimeImmutable(self::TODAY)));

        self::assertSame('available', $result['status']);
        self::assertGreaterThan(0, $expected);
        self::assertSame($expected, $result['amounts']['realExpensesCents'] ?? null);
        self::assertSame(['entries' => 0, 'trips' => 1, 'travelAllowanceCents' => $expected], $result['origins']['realExpenses']);
        self::assertContains('travel_kilometric_allowance_cents', array_column($result['rules'], 'code'));
    }

    private function calculator(): KilometricAllowanceCalculator
    {
        return new KilometricAllowanceCalculator(new class implements BaremeKilometriqueRepositoryInterface {
            public function findByYear(int $year): ?BaremeKilometrique
            {
                return null;
            }

            public function findAll(): array
            {
                return [];
            }

            public function save(BaremeKilometrique $b): void
            {
            }
        });
    }

    /**
     * @param list<NetTaxableSalary>      $salaries
     * @param list<Expense>               $expenses
     * @param list<EmployerReimbursement> $reimbursements
     */
    private function handler(array $salaries, array $expenses, array $reimbursements, ?FiscalConfig $config): GetTaxComparisonQueryHandler
    {
        return new GetTaxComparisonQueryHandler(
            new class($salaries) implements NetTaxableSalaryRepositoryInterface {
                /** @param list<NetTaxableSalary> $rows */
                public function __construct(private array $rows)
                {
                }

                public function save(NetTaxableSalary $salary): void
                {
                }

                public function remove(NetTaxableSalary $salary): void
                {
                }

                public function findById(string $id): ?NetTaxableSalary
                {
                    return null;
                }

                public function findByPersonAndYear(string $personId, int $year): array
                {
                    return $this->rows;
                }
            },
            new class($reimbursements) implements EmployerReimbursementRepositoryInterface {
                /** @param list<EmployerReimbursement> $rows */
                public function __construct(private array $rows)
                {
                }

                public function save(EmployerReimbursement $reimbursement): void
                {
                }

                public function remove(EmployerReimbursement $reimbursement): void
                {
                }

                public function findById(string $id): ?EmployerReimbursement
                {
                    return null;
                }

                public function findByPersonAndYear(string $personId, int $year): array
                {
                    return $this->rows;
                }
            },
            new class($expenses) implements ExpenseRepositoryInterface {
                /** @param list<Expense> $rows */
                public function __construct(private array $rows)
                {
                }

                public function save(Expense $expense): void
                {
                }

                public function delete(Expense $expense): void
                {
                }

                public function findById(ExpenseId $id): ?Expense
                {
                    return null;
                }

                public function findByPersonAndPeriod(string $personId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
                {
                    return [];
                }

                public function findByPersonAndYear(string $personId, int $year): array
                {
                    return $this->rows;
                }

                public function findReceiptPage(string $personId, int $year, int $offset, int $limit, string $status = 'all', string $search = ''): array
                {
                    return [];
                }

                public function receiptVaultCounts(string $personId, int $year, string $status = 'all', string $search = ''): array
                {
                    return ['total' => 0, 'withReceipt' => 0, 'missing' => 0, 'filtered' => 0];
                }

                public function findByPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): array
                {
                    return [];
                }

                public function countByPersonId(string $personId): int
                {
                    return 0;
                }
            },
            new class($config) implements FiscalConfigRepositoryInterface {
                public function __construct(private ?FiscalConfig $config)
                {
                }

                public function findByYear(int $year): ?FiscalConfig
                {
                    // Deliberately returns the stored configuration whatever the year: it must be rejected.
                    return $this->config;
                }

                public function findAll(): array
                {
                    return null === $this->config ? [] : [$this->config];
                }

                public function save(FiscalConfig $config): void
                {
                }
            },
            $this->calculator(),
        );
    }

    private function salary(string $id, string $personId, int $year, int $cents): NetTaxableSalary
    {
        return new NetTaxableSalary($id, $personId, 'Employer '.$personId, $year, new \DateTimeImmutable($year.'-01-01'), new \DateTimeImmutable($year.'-12-31'), $cents);
    }

    private function config(int $year): FiscalConfig
    {
        return new FiscalConfig($year, '2.70', '5.35', 10, 50_000, 1_400_000, $year.'.1', 'BOFiP-IR-BASE-'.$year, new \DateTimeImmutable($year.'-01-01'));
    }
}
