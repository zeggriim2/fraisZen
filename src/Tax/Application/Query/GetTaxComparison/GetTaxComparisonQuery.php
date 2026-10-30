<?php

declare(strict_types=1);

namespace App\Tax\Application\Query\GetTaxComparison;

/**
 * Forfait / real expenses comparison of ONE person for ONE year.
 *
 * $today is provided by the caller (HTTP layer clock) so that the temporal qualification
 * of the estimate (current year vs. elapsed year) stays deterministic in tests.
 */
final readonly class GetTaxComparisonQuery
{
    public function __construct(
        public string $personId,
        public int $year,
        public \DateTimeImmutable $today,
    ) {
    }
}
