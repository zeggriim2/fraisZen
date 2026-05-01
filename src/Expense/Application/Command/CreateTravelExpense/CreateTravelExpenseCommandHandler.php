<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\CreateTravelExpense;

use App\Expense\Domain\Entity\TravelExpense;
use App\Expense\Domain\Enum\VehicleType;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateTravelExpenseCommandHandler implements CommandHandlerInterface
{
    public function __construct(private ExpenseRepositoryInterface $repository)
    {
    }

    public function __invoke(CreateTravelExpenseCommand $command): string
    {
        $id = ExpenseId::generate();
        $this->repository->save(new TravelExpense(
            id: $id,
            personId: $command->personId,
            date: new \DateTimeImmutable($command->date),
            description: $command->description,
            departure: $command->departure,
            arrival: $command->arrival,
            distanceKm: $command->distanceKm,
            vehiclePower: $command->vehiclePower,
            roundTrip: $command->roundTrip,
            vehicleType: VehicleType::from($command->vehicleType),
            isElectric: $command->isElectric,
        ));

        return $id->value();
    }
}
