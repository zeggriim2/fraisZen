<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\CreateTollExpense;

use App\Expense\Domain\Entity\TollExpense;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateTollExpenseCommandHandler implements CommandHandlerInterface
{
    public function __construct(private ExpenseRepositoryInterface $repository)
    {
    }

    public function __invoke(CreateTollExpenseCommand $command): string
    {
        $id = ExpenseId::generate();
        $this->repository->save(new TollExpense(
            id: $id,
            personId: $command->personId,
            date: new \DateTimeImmutable($command->date),
            description: $command->description,
            tollAmount: $command->amount,
            departure: $command->departure,
            arrival: $command->arrival,
        ));

        return $id->value();
    }
}
