<?php

declare(strict_types=1);

namespace App\Expense\Application\Query\GetExpensesSummary;

final readonly class GetExpensesSummaryQuery
{
    public function __construct(
        public string $personId,
        public int $year,
    ) {}
}
