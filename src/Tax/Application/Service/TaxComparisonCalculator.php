<?php

declare(strict_types=1);

namespace App\Tax\Application\Service;

use App\Admin\Domain\Entity\FiscalConfig;
use App\Admin\Domain\Repository\FiscalConfigRepositoryInterface;
use App\Expense\Domain\Entity\Expense;
use App\Expense\Domain\Entity\TravelExpense;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\Service\KilometricAllowanceCalculator;
use App\Tax\Domain\Entity\EmployerReimbursement;
use App\Tax\Domain\Entity\NetTaxableSalary;
use App\Tax\Domain\Repository\EmployerReimbursementRepositoryInterface;
use App\Tax\Domain\Repository\NetTaxableSalaryRepositoryInterface;
use App\Tax\Domain\Service\TaxComparisonService;

/**
 * Loads the CURRENT inputs of ONE person and ONE year and runs the comparison engine.
 *
 * Nothing is cached: every call recomputes from the current salaries, expenses, reimbursements and
 * fiscal configuration, so any modification before closing invalidates the previous result and the
 * next read is consistent with the current data. Nothing is ever consolidated across persons.
 */
final readonly class TaxComparisonCalculator
{
    public function __construct(
        private NetTaxableSalaryRepositoryInterface $salaries,
        private EmployerReimbursementRepositoryInterface $reimbursements,
        private ExpenseRepositoryInterface $expenses,
        private FiscalConfigRepositoryInterface $fiscalConfigs,
        private KilometricAllowanceCalculator $kilometricCalculator,
        private TaxComparisonService $service = new TaxComparisonService(),
    ) {
    }

    public function compute(string $personId, int $year): ComputedTaxComparison
    {
        $salaries = array_values(array_filter(
            $this->salaries->findByPersonAndYear($personId, $year),
            static fn (NetTaxableSalary $s): bool => $s->personId() === $personId && $s->year() === $year,
        ));
        $reimbursements = array_values(array_filter(
            $this->reimbursements->findByPersonAndYear($personId, $year),
            static fn (EmployerReimbursement $r): bool => $r->personId() === $personId && $r->year() === $year,
        ));
        $expenses = array_values(array_filter(
            $this->expenses->findByPersonAndYear($personId, $year),
            static fn (Expense $e): bool => $e->personId() === $personId && (int) $e->date()->format('Y') === $year,
        ));

        $trips = [];
        foreach ($expenses as $expense) {
            if ($expense instanceof TravelExpense) {
                $trips[] = [
                    'distanceKm' => $expense->effectiveDistanceKm(),
                    'vehiclePower' => $expense->vehiclePower(),
                    'vehicleType' => $expense->vehicleType()->value,
                    'isElectric' => $expense->isElectric(),
                ];
            }
        }
        $travelAllowanceCents = [] === $trips
            ? null
            : (int) round($this->kilometricCalculator->calculateAnnualDeduction($trips, $year) * 100.0);

        $fiscalConfig = $this->fiscalConfigs->findByYear($year);
        $result = $this->service->compare($personId, $year, $salaries, $reimbursements, $expenses, $fiscalConfig, $travelAllowanceCents);

        $employers = array_values(array_unique(array_map(static fn (NetTaxableSalary $s): string => $s->employer(), $salaries)));
        $origins = [
            'netTaxableSalary' => ['entries' => \count($salaries), 'employers' => $employers],
            'realExpenses' => [
                'entries' => \count($expenses) - \count($trips),
                'trips' => \count($trips),
                'travelAllowanceCents' => $travelAllowanceCents,
            ],
            'reimbursements' => [
                'entries' => \count($reimbursements),
                'reintegrated' => \count(array_filter($reimbursements, static fn (EmployerReimbursement $r): bool => true === $r->toReintegrate())),
            ],
        ];

        $retainedData = [
            'personId' => $personId,
            'year' => $year,
            'netTaxableSalaries' => self::sortById(array_map(static fn (NetTaxableSalary $s): array => [
                'id' => $s->id(),
                'employer' => $s->employer(),
                'periodStart' => $s->periodStart()->format('Y-m-d'),
                'periodEnd' => $s->periodEnd()->format('Y-m-d'),
                'amountCents' => $s->amountCents(),
            ], $salaries)),
            'employerReimbursements' => self::sortById(array_map(static fn (EmployerReimbursement $r): array => [
                'id' => $r->id(),
                'employer' => $r->employer(),
                'date' => $r->date()?->format('Y-m-d'),
                'amountCents' => $r->amountCents(),
                'category' => $r->category(),
                'toReintegrate' => $r->toReintegrate(),
                'expenseId' => $r->expenseId(),
            ], $reimbursements)),
            'expenses' => self::sortById(array_map(self::expense(...), $expenses)),
            'travelAllowanceCents' => $travelAllowanceCents,
            'fiscalConfiguration' => null === $fiscalConfig ? null : self::fiscalConfiguration($fiscalConfig),
        ];

        $fingerprint = hash('sha256', json_encode(
            ['retainedData' => $retainedData, 'calculation' => $result->toArray()],
            \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_UNICODE,
        ));

        return new ComputedTaxComparison($result, $origins, $retainedData, $fingerprint);
    }

    /** @return array<string, mixed> */
    private static function expense(Expense $expense): array
    {
        $row = [
            'id' => $expense->id()->value(),
            'type' => $expense->type()->value,
            'date' => $expense->date()->format('Y-m-d'),
            'amountCents' => (int) round($expense->amount() * 100.0),
        ];
        if ($expense instanceof TravelExpense) {
            $row['distanceKm'] = $expense->effectiveDistanceKm();
            $row['vehiclePower'] = $expense->vehiclePower();
            $row['vehicleType'] = $expense->vehicleType()->value;
            $row['isElectric'] = $expense->isElectric();
        }

        return $row;
    }

    /** @return array<string, int|string|null> */
    private static function fiscalConfiguration(FiscalConfig $config): array
    {
        return [
            'year' => $config->year(),
            'forfaitRatePercent' => $config->forfaitRatePercent(),
            'forfaitMinimumCents' => $config->forfaitMinimumCents(),
            'forfaitMaximumCents' => $config->forfaitMaximumCents(),
            'version' => $config->version(),
            'source' => $config->source(),
            'effectiveFrom' => $config->effectiveFrom()?->format('Y-m-d'),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    private static function sortById(array $rows): array
    {
        usort($rows, static fn (array $a, array $b): int => (string) $a['id'] <=> (string) $b['id']);

        return $rows;
    }
}
