<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Support;

use App\Tax\Domain\Entity\EmployerReimbursement;
use App\Tax\Domain\Repository\EmployerReimbursementRepositoryInterface;

final class InMemoryEmployerReimbursements implements EmployerReimbursementRepositoryInterface
{
    /** @var array<string, EmployerReimbursement> */
    public array $rows = [];

    public function save(EmployerReimbursement $reimbursement): void
    {
        $this->rows[$reimbursement->id()] = $reimbursement;
    }

    public function remove(EmployerReimbursement $reimbursement): void
    {
        unset($this->rows[$reimbursement->id()]);
    }

    public function findById(string $id): ?EmployerReimbursement
    {
        return $this->rows[$id] ?? null;
    }

    public function findByPersonAndYear(string $personId, int $year): array
    {
        return array_values(array_filter($this->rows, static fn (EmployerReimbursement $r): bool => $r->personId() === $personId && $r->year() === $year));
    }
}
