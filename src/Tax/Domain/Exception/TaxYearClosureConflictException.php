<?php

declare(strict_types=1);

namespace App\Tax\Domain\Exception;

use App\SharedKernel\Domain\Exception\ConflictException;

/**
 * The closing or the reopening of a year conflicts with the current state (HTTP 409):
 * the year is already closed, the validated calculation is stale, or another request changed
 * the closure concurrently. The existing snapshot is never replaced.
 */
final class TaxYearClosureConflictException extends ConflictException
{
    public static function alreadyClosed(int $year, int $version): self
    {
        return new self(\sprintf('Year %d is already closed (version %d): reopen it explicitly before closing it again.', $year, $version));
    }

    public static function staleCalculation(int $year): self
    {
        return new self(\sprintf('The calculation of year %d changed since it was validated: review the new result before closing.', $year));
    }

    public static function notClosed(int $year): self
    {
        return new self(\sprintf('Year %d is not closed.', $year));
    }

    public static function versionMismatch(int $year, int $expected, int $actual): self
    {
        return new self(\sprintf('The closure of year %d is at version %d, not %d.', $year, $actual, $expected));
    }

    public static function concurrentModification(): self
    {
        return new self('The closure was modified by another request: reload the result.');
    }
}
