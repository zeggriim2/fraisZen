<?php

declare(strict_types=1);

namespace App\Expense\Application\Query\GetExpensesSummary;

use App\Expense\Domain\Entity\MealExpense;
use App\Expense\Domain\Entity\RemoteWorkExpense;
use App\Expense\Domain\Entity\TollExpense;
use App\Expense\Domain\Entity\TravelExpense;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\Service\KilometricAllowanceCalculator;
use App\SharedKernel\Application\Bus\QueryHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetExpensesSummaryQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private ExpenseRepositoryInterface $repository,
        private KilometricAllowanceCalculator $calculator,
    ) {}

    public function __invoke(GetExpensesSummaryQuery $query): array
    {
        $expenses = $this->repository->findByPersonAndYear($query->personId, $query->year);

        $trips = [];
        $remoteWorkDays = 0;
        $tollTotal = 0.0;
        $mealEntries = [];

        foreach ($expenses as $expense) {
            if ($expense instanceof TravelExpense) {
                $trips[] = [
                    'distanceKm'   => $expense->effectiveDistanceKm(),
                    'vehiclePower' => $expense->vehiclePower(),
                    'vehicleType'  => $expense->vehicleType()->value,
                    'isElectric'   => $expense->isElectric(),
                    'date'         => $expense->date()->format('Y-m-d'),
                    'departure'    => $expense->departure(),
                    'arrival'      => $expense->arrival(),
                    'description'  => $expense->description(),
                    'roundTrip'    => $expense->roundTrip(),
                ];
            } elseif ($expense instanceof RemoteWorkExpense) {
                ++$remoteWorkDays;
            } elseif ($expense instanceof TollExpense) {
                $tollTotal += $expense->amount();
            } elseif ($expense instanceof MealExpense) {
                $mealEntries[] = $expense->amount();
            }
        }

        $travelDeduction     = $this->calculator->calculateAnnualDeduction($trips, $query->year);
        $remoteWorkDeduction = round($remoteWorkDays * 2.50, 2);
        $mealDeduction       = round(array_sum($mealEntries), 2);

        return [
            'personId'   => $query->personId,
            'year'       => $query->year,
            'travel'     => [
                'trips'     => $trips,
                'totalKm'   => array_sum(array_column($trips, 'distanceKm')),
                'deduction' => $travelDeduction,
            ],
            'remoteWork' => [
                'days'           => $remoteWorkDays,
                'dailyAllowance' => 2.50,
                'deduction'      => $remoteWorkDeduction,
            ],
            'toll'       => [
                'entries'   => count(array_filter($expenses, fn ($e) => $e instanceof TollExpense)),
                'deduction' => round($tollTotal, 2),
            ],
            'meal'       => [
                'entries'   => count($mealEntries),
                'deduction' => $mealDeduction,
            ],
            'total'      => round($travelDeduction + $remoteWorkDeduction + $tollTotal + $mealDeduction, 2),
        ];
    }
}
