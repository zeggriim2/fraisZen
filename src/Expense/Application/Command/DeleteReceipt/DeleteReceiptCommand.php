<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\DeleteReceipt;

final readonly class DeleteReceiptCommand
{
    public function __construct(public string $expenseId)
    {
    }
}
