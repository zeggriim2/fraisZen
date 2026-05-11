<?php

declare(strict_types=1);

namespace App\Expense\Application\Query\GetBaremeKilometriqueQuery;

final readonly class GetBaremeKilometriqueQuery
{
    public function __construct(
        public int $year,
    ) {
    }
}
