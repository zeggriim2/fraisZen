<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\CreateParkingExpense;

use App\Expense\Domain\Entity\ParkingExpense;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateParkingExpenseCommandHandler implements CommandHandlerInterface
{
    public function __construct(private ExpenseRepositoryInterface $repository) {}

    public function __invoke(CreateParkingExpenseCommand $command): string
    {
        $id = ExpenseId::generate();
        $this->repository->save(new ParkingExpense(
            id: $id,
            personId: $command->personId,
            date: new \DateTimeImmutable($command->date),
            description: $command->description,
            parkingAmount: $command->amount,
            location: $command->location,
        ));
        return $id->value();
    }
}