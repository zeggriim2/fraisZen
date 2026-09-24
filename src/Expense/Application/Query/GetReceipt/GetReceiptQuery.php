<?php

declare(strict_types=1);

namespace App\Expense\Application\Query\GetReceipt;

final readonly class GetReceiptQuery
{
    public function __construct(public string $expenseId)
    {
    }
}
