<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Support;

use App\Tax\Domain\Entity\NetTaxableSalary;
use App\Tax\Domain\Repository\NetTaxableSalaryRepositoryInterface;

final class InMemoryNetTaxableSalaries implements NetTaxableSalaryRepositoryInterface
{
    /** @var array<string, NetTaxableSalary> */
    public array $rows = [];

    public function save(NetTaxableSalary $salary): void
    {
        $this->rows[$salary->id()] = $salary;
    }

    public function remove(NetTaxableSalary $salary): void
    {
        unset($this->rows[$salary->id()]);
    }

    public function findById(string $id): ?NetTaxableSalary
    {
        return $this->rows[$id] ?? null;
    }

    public function findByPersonAndYear(string $personId, int $year): array
    {
        return array_values(array_filter($this->rows, static fn (NetTaxableSalary $s): bool => $s->personId() === $personId && $s->year() === $year));
    }
}
