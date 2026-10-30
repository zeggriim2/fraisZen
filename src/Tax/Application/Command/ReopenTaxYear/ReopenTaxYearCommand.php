<?php

declare(strict_types=1);

namespace App\Tax\Application\Command\ReopenTaxYear;

/**
 * Explicitly reopens the closed year of ONE person. $version is the closure version the user saw:
 * reopening another (newer) version is refused. The snapshot is kept in the history.
 */
final readonly class ReopenTaxYearCommand
{
    public function __construct(
        public string $personId,
        public int $year,
        public string $userId,
        public int $version,
        public \DateTimeImmutable $reopenedAt,
    ) {
    }
}
