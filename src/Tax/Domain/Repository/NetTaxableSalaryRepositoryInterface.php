<?php

declare(strict_types=1);

namespace App\Tax\Domain\Repository;

use App\Tax\Domain\Entity\NetTaxableSalary;

/** @psalm-suppress PossiblyUnusedMethod */
interface NetTaxableSalaryRepositoryInterface
{
    public function save(NetTaxableSalary $salary): void;

    /** @return list<NetTaxableSalary> */
    public function findByPersonAndYear(string $personId, int $year): array;
}
