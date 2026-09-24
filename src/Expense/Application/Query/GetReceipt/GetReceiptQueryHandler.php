<?php

declare(strict_types=1);

namespace App\Expense\Application\Query\GetReceipt;

use App\Expense\Application\Receipt\ReceiptStorageInterface;
use App\Expense\Domain\Exception\ExpenseNotFoundException;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\SharedKernel\Application\Bus\QueryHandlerInterface;

final readonly class GetReceiptQueryHandler implements QueryHandlerInterface
{
    public function __construct(private ExpenseRepositoryInterface $repository, private ReceiptStorageInterface $storage)
    {
    }

    /** @return array{path: string, filename: string, mimeType: string} */
    public function __invoke(GetReceiptQuery $query): array
    {
        $expense = $this->repository->findById(ExpenseId::fromString($query->expenseId));
        if (null === $expense || null === $expense->receiptFilename()) {
            throw new ExpenseNotFoundException($query->expenseId);
        }
        $path = $this->storage->find($query->expenseId);
        if (null === $path) {
            throw new ExpenseNotFoundException($query->expenseId);
        }

        return ['path' => $path, 'filename' => $expense->receiptFilename(), 'mimeType' => $expense->receiptMimeType() ?? 'application/octet-stream'];
    }
}
