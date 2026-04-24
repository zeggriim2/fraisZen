<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\DeleteExpense;

final readonly class DeleteExpenseCommand
{
    public function __construct(public string $id) {}
}
