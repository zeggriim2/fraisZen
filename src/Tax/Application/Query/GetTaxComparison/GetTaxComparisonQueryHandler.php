<?php

declare(strict_types=1);

namespace App\Tax\Application\Query\GetTaxComparison;

use App\Admin\Domain\Repository\FiscalConfigRepositoryInterface;
use App\Expense\Domain\Entity\Expense;
use App\Expense\Domain\Entity\TravelExpense;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\Service\KilometricAllowanceCalculator;
use App\SharedKernel\Application\Bus\QueryHandlerInterface;
use App\Tax\Application\Presenter\TaxComparisonPresenter;
use App\Tax\Domain\Entity\EmployerReimbursement;
use App\Tax\Domain\Entity\NetTaxableSalary;
use App\Tax\Domain\Repository\EmployerReimbursementRepositoryInterface;
use App\Tax\Domain\Repository\NetTaxableSalaryRepositoryInterface;
use App\Tax\Domain\Service\TaxComparisonService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Loads the inputs of ONE person and ONE year, runs the comparison engine and returns
 * the display-ready payload. Nothing is ever consolidated across persons.
 *
 * @psalm-import-type Presented from TaxComparisonPresenter
 */
#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetTaxComparisonQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private NetTaxableSalaryRepositoryInterface $salaries,
        private EmployerReimbursementRepositoryInterface $reimbursements,
        private ExpenseRepositoryInterface $expenses,
        private FiscalConfigRepositoryInterface $fiscalConfigs,
        private KilometricAllowanceCalculator $kilometricCalculator,
        private TaxComparisonService $service = new TaxComparisonService(),
        private TaxComparisonPresenter $presenter = new TaxComparisonPresenter(),
    ) {
    }

    /** @return Presented */
    public function __invoke(GetTaxComparisonQuery $query): array
    {
        $salaries = array_values(array_filter(
            $this->salaries->findByPersonAndYear($query->personId, $query->year),
            static fn (NetTaxableSalary $s): bool => $s->personId() === $query->personId && $s->year() === $query->year,
        ));
        $reimbursements = array_values(array_filter(
            $this->reimbursements->findByPersonAndYear($query->personId, $query->year),
            static fn (EmployerReimbursement $r): bool => $r->personId() === $query->personId && $r->year() === $query->year,
        ));
        $expenses = array_values(array_filter(
            $this->expenses->findByPersonAndYear($query->personId, $query->year),
            static fn (Expense $e): bool => $e->personId() === $query->personId && (int) $e->date()->format('Y') === $query->year,
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
            : (int) round($this->kilometricCalculator->calculateAnnualDeduction($trips, $query->year) * 100.0);

        $result = $this->service->compare(
            $query->personId,
            $query->year,
            $salaries,
            $reimbursements,
            $expenses,
            $this->fiscalConfigs->findByYear($query->year),
            $travelAllowanceCents,
        );

        $employers = array_values(array_unique(array_map(static fn (NetTaxableSalary $s): string => $s->employer(), $salaries)));

        return $this->presenter->present($result, $query->today, [
            'netTaxableSalary' => ['entries' => count($salaries), 'employers' => $employers],
            'realExpenses' => [
                'entries' => count($expenses) - count($trips),
                'trips' => count($trips),
                'travelAllowanceCents' => $travelAllowanceCents,
            ],
            'reimbursements' => [
                'entries' => count($reimbursements),
                'reintegrated' => count(array_filter($reimbursements, static fn (EmployerReimbursement $r): bool => true === $r->toReintegrate())),
            ],
        ]);
    }
}
