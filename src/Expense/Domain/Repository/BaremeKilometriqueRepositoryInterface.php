<?php

declare(strict_types=1);

namespace App\Expense\Domain\Repository;

use App\Expense\Domain\Entity\BaremeKilometrique;

interface BaremeKilometriqueRepositoryInterface
{
    public function findByYear(int $year): ?BaremeKilometrique;

    /** @return BaremeKilometrique[] */
    public function findAll(): array;

    public function save(BaremeKilometrique $bareme): void;
}