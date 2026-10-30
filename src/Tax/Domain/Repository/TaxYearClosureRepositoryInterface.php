<?php

declare(strict_types=1);

namespace App\Tax\Domain\Repository;

use App\Tax\Domain\Entity\TaxYearClosure;

/** @psalm-suppress PossiblyUnusedMethod */
interface TaxYearClosureRepositoryInterface
{
    /**
     * Persists a NEW closure. A concurrent closing of the same person, year and version is
     * rejected with a TaxYearClosureConflictException (unique constraint), never overwritten.
     */
    public function add(TaxYearClosure $closure): void;

    /**
     * Flushes the reopening of an existing closure; a concurrent modification is rejected
     * with a TaxYearClosureConflictException (optimistic lock).
     */
    public function save(TaxYearClosure $closure): void;

    /** The closure currently in force (not reopened), if any. */
    public function findActive(string $personId, int $year): ?TaxYearClosure;

    /** @return list<TaxYearClosure> every version of the person and year, oldest first */
    public function findHistory(string $personId, int $year): array;
}
