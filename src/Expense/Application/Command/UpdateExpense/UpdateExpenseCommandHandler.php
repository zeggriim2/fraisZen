<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\UpdateExpense;

use App\Expense\Domain\Entity\MealExpense;
use App\Expense\Domain\Entity\ParkingExpense;
use App\Expense\Domain\Entity\TollExpense;
use App\Expense\Domain\Entity\TravelExpense;
use App\Expense\Domain\Enum\VehicleType;
use App\Expense\Domain\Exception\ExpenseNotFoundException;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateExpenseCommandHandler implements CommandHandlerInterface
{
    public function __construct(private ExpenseRepositoryInterface $repository)
    {
    }

    public function __invoke(UpdateExpenseCommand $command): void
    {
        $expense = $this->repository->findById(ExpenseId::fromString($command->id));

        if (null === $expense) {
            throw new ExpenseNotFoundException($command->id);
        }

        $f = $command->fields;

        if (array_key_exists('description', $f)) {
            $expense->setDescription($f['description'] ?: null);
        }

        if ($expense instanceof TravelExpense) {
            if (array_key_exists('departure', $f)) {
                $expense->setDeparture($f['departure'] ?: null);
            }
            if (array_key_exists('arrival', $f)) {
                $expense->setArrival($f['arrival'] ?: null);
            }
            if (array_key_exists('distanceKm', $f)) {
                $expense->setDistanceKm((float) $f['distanceKm']);
            }
            if (array_key_exists('vehiclePower', $f)) {
                $expense->setVehiclePower(isset($f['vehiclePower']) ? (int) $f['vehiclePower'] : null);
            }
            if (array_key_exists('roundTrip', $f)) {
                $expense->setRoundTrip((bool) $f['roundTrip']);
            }
            if (array_key_exists('vehicleType', $f)) {
                $expense->setVehicleType(VehicleType::from($f['vehicleType']));
            }
            if (array_key_exists('isElectric', $f)) {
                $expense->setIsElectric((bool) $f['isElectric']);
            }
        } elseif ($expense instanceof TollExpense) {
            if (array_key_exists('amount', $f)) {
                $expense->setTollAmount((float) $f['amount']);
            }
            if (array_key_exists('departure', $f)) {
                $expense->setDeparture($f['departure'] ?: null);
            }
            if (array_key_exists('arrival', $f)) {
                $expense->setArrival($f['arrival'] ?: null);
            }
        } elseif ($expense instanceof MealExpense) {
            if (array_key_exists('mealAmount', $f)) {
                $expense->setMealAmount((float) $f['mealAmount']);
            }
            if (array_key_exists('employerTicketContribution', $f)) {
                $expense->setEmployerTicketContribution((float) $f['employerTicketContribution']);
            }
            if (array_key_exists('withoutReceipt', $f)) {
                $expense->setWithoutReceipt((bool) $f['withoutReceipt']);
            }
        } elseif ($expense instanceof ParkingExpense) {
            if (array_key_exists('amount', $f)) {
                $expense->setParkingAmount((float) $f['amount']);
            }
            if (array_key_exists('location', $f)) {
                $expense->setLocation($f['location'] ?: null);
            }
        }

        $this->repository->save($expense);
    }
}
