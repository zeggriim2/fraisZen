<?php

declare(strict_types=1);

namespace App\Expense\Domain\Repository;

use App\Expense\Domain\Entity\Expense;
use App\Expense\Domain\ValueObject\ExpenseId;

interface ExpenseRepositoryInterface
{
    public function save(Expense $expense): void;

    public function delete(Expense $expense): void;

    public function findById(ExpenseId $id): ?Expense;

    /** @return Expense[] */
    public function findByPersonAndPeriod(string $personId, \DateTimeImmutable $from, \DateTimeImmutable $to): array;

    /** @return Expense[] */
    public function findByPersonAndYear(string $personId, int $year): array;

    /** @return Expense[] */
    public function findReceiptPage(
        string $personId,
        int $year,
        int $offset,
        int $limit,
        string $status = 'all',
        string $search = '',
    ): array;

    /** @return array{total: int, withReceipt: int, missing: int, filtered: int} */
    public function receiptVaultCounts(string $personId, int $year, string $status = 'all', string $search = ''): array;

    /** @return Expense[] */
    public function findByPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): array;

    public function countByPersonId(string $personId): int;
}
