<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Support;

use App\Tax\Domain\Entity\TaxYearClosure;
use App\Tax\Domain\Exception\TaxYearClosureConflictException;
use App\Tax\Domain\Repository\TaxYearClosureRepositoryInterface;

/**
 * In-memory closures enforcing the same unique (person, year, version) rule as the database.
 * `$staleReads` simulates a concurrent request: reads return nothing although rows exist.
 */
final class InMemoryTaxYearClosures implements TaxYearClosureRepositoryInterface
{
    /** @var list<TaxYearClosure> */
    public array $rows = [];
    public bool $staleReads = false;
    public int $saves = 0;

    public function add(TaxYearClosure $closure): void
    {
        foreach ($this->rows as $row) {
            if ($row->personId() === $closure->personId() && $row->year() === $closure->year() && $row->version() === $closure->version()) {
                throw TaxYearClosureConflictException::alreadyClosed($closure->year(), $closure->version());
            }
        }
        $this->rows[] = $closure;
    }

    public function save(TaxYearClosure $closure): void
    {
        ++$this->saves;
    }

    public function findActive(string $personId, int $year): ?TaxYearClosure
    {
        foreach (array_reverse($this->findHistory($personId, $year)) as $closure) {
            if ($closure->isClosed()) {
                return $closure;
            }
        }

        return null;
    }

    public function findHistory(string $personId, int $year): array
    {
        if ($this->staleReads) {
            return [];
        }
        $rows = array_values(array_filter($this->rows, static fn (TaxYearClosure $c): bool => $c->personId() === $personId && $c->year() === $year));
        usort($rows, static fn (TaxYearClosure $a, TaxYearClosure $b): int => $a->version() <=> $b->version());

        return $rows;
    }
}
