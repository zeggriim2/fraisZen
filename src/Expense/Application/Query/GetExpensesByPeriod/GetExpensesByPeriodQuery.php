<?php

declare(strict_types=1);

namespace App\Expense\Application\Query\GetExpensesByPeriod;

final readonly class GetExpensesByPeriodQuery
{
    public function __construct(
        public string $from,
        public string $to,
        public ?string $personId = null,
    ) {}
}
