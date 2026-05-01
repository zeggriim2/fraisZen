<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\UpdateExpense;

final readonly class UpdateExpenseCommand
{
    public function __construct(
        public string $id,
        public array $fields,
    ) {
    }
}
