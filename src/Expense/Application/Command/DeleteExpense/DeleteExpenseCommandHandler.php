<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\DeleteExpense;

use App\Expense\Domain\Exception\ExpenseNotFoundException;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeleteExpenseCommandHandler implements CommandHandlerInterface
{
    public function __construct(private ExpenseRepositoryInterface $repository)
    {
    }

    public function __invoke(DeleteExpenseCommand $command): void
    {
        $expense = $this->repository->findById(ExpenseId::fromString($command->id))
            ?? throw ExpenseNotFoundException::withId($command->id);

        $this->repository->delete($expense);
    }
}
