<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\CreateRemoteWorkExpense;

final readonly class CreateRemoteWorkExpenseCommand
{
    public function __construct(
        public string $personId,
        public string $date,
        public ?string $description = null,
    ) {}
}
