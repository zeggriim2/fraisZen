<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\BulkCreateTravelExpense;

use App\Expense\Domain\Entity\TravelExpense;
use App\Expense\Domain\Enum\VehicleType;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class BulkCreateTravelExpenseCommandHandler implements CommandHandlerInterface
{
    public function __construct(private ExpenseRepositoryInterface $repository)
    {
    }

    public function __invoke(BulkCreateTravelExpenseCommand $command): int
    {
        $count = 0;
        $vehicleType = VehicleType::from($command->vehicleType);

        foreach ($command->dates as $date) {
            $this->repository->save(new TravelExpense(
                id: ExpenseId::generate(),
                personId: $command->personId,
                date: new \DateTimeImmutable($date),
                description: $command->description,
                departure: $command->departure,
                arrival: $command->arrival,
                distanceKm: $command->distanceKm,
                vehiclePower: $command->vehiclePower,
                roundTrip: $command->roundTrip,
                vehicleType: $vehicleType,
                isElectric: $command->isElectric,
            ));
            ++$count;
        }

        return $count;
    }
}
