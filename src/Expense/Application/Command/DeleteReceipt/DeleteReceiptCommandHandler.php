<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\DeleteReceipt;

use App\Expense\Application\Receipt\ReceiptStorageInterface;
use App\Expense\Domain\Exception\ExpenseNotFoundException;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;

final readonly class DeleteReceiptCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private ExpenseRepositoryInterface $repository,
        private ReceiptStorageInterface $storage,
    ) {
    }

    public function __invoke(DeleteReceiptCommand $command): void
    {
        $expense = $this->repository->findById(ExpenseId::fromString($command->expenseId));

        if (null === $expense) {
            throw new ExpenseNotFoundException($command->expenseId);
        }

        $this->storage->delete($command->expenseId);
        $expense->setReceipt(null, null);
        $expense->setReceiptMetadata(null, null, null);
        $this->repository->save($expense);
    }
}
