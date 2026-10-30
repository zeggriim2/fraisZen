<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Support;

use App\Expense\Domain\Entity\Expense;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;

/** Expenses of every person: the tested code must filter by person and year itself. */
final class InMemoryExpenses implements ExpenseRepositoryInterface
{
    /** @var list<Expense> */
    public array $rows = [];

    public function save(Expense $expense): void
    {
        $this->rows[] = $expense;
    }

    public function delete(Expense $expense): void
    {
        $this->rows = array_values(array_filter($this->rows, static fn (Expense $e): bool => $e !== $expense));
    }

    public function findById(\App\Expense\Domain\ValueObject\ExpenseId $id): ?Expense
    {
        foreach ($this->rows as $expense) {
            if ($expense->id()->value() === $id->value()) {
                return $expense;
            }
        }

        return null;
    }

    public function findByPersonAndPeriod(string $personId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return [];
    }

    public function findByPersonAndYear(string $personId, int $year): array
    {
        return $this->rows;
    }

    public function findReceiptPage(string $personId, int $year, int $offset, int $limit, string $status = 'all', string $search = ''): array
    {
        return [];
    }

    public function receiptVaultCounts(string $personId, int $year, string $status = 'all', string $search = ''): array
    {
        return ['total' => 0, 'withReceipt' => 0, 'missing' => 0, 'filtered' => 0];
    }

    public function findByPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return [];
    }

    public function countByPersonId(string $personId): int
    {
        return 0;
    }
}
