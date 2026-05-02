<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\UpdateExpense;

final readonly class UpdateExpenseCommand
{
    /**
     * @param array<string, mixed> $fields
     */
    public function __construct(
        public string $id,
        public array $fields,
    ) {
    }
}
