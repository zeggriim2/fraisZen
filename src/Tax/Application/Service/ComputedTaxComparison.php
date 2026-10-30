<?php

declare(strict_types=1);

namespace App\Tax\Application\Service;

use App\Tax\Domain\Service\TaxComparisonResult;

/**
 * Live calculation of ONE person and ONE year, with everything needed to validate it and close it:
 * the engine result, its origins, every retained input and a fingerprint of both.
 *
 * The fingerprint changes as soon as a salary, an expense, a reimbursement, the fiscal rules or the
 * person an input is attached to changes: it is what the user validates before closing, and what
 * reveals a difference between a closed snapshot and the current data.
 *
 * @psalm-import-type Origins from \App\Tax\Application\Presenter\TaxComparisonPresenter
 */
final readonly class ComputedTaxComparison
{
    /**
     * @param Origins              $origins
     * @param array<string, mixed> $retainedData
     */
    public function __construct(
        public TaxComparisonResult $result,
        public array $origins,
        public array $retainedData,
        public string $fingerprint,
    ) {
    }
}
