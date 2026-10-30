<?php

declare(strict_types=1);

namespace App\Tax\Application\Command\CloseTaxYear;

/**
 * Validates and closes the comparison of ONE person for ONE year.
 *
 * $calculationFingerprint is the fingerprint of the calculation the user reviewed (returned by the
 * comparison query): closing is refused if the data or rules changed in the meantime.
 */
final readonly class CloseTaxYearCommand
{
    public function __construct(
        public string $personId,
        public int $year,
        public string $userId,
        public string $calculationFingerprint,
        public \DateTimeImmutable $closedAt,
    ) {
    }
}
