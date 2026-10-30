<?php

declare(strict_types=1);

namespace App\Tax\Domain\Repository;

use App\Tax\Domain\Entity\EmployerReimbursement;

/** @psalm-suppress PossiblyUnusedMethod */
interface EmployerReimbursementRepositoryInterface
{
    public function save(EmployerReimbursement $reimbursement): void;

    public function remove(EmployerReimbursement $reimbursement): void;

    public function findById(string $id): ?EmployerReimbursement;

    /** @return list<EmployerReimbursement> */
    public function findByPersonAndYear(string $personId, int $year): array;
}
