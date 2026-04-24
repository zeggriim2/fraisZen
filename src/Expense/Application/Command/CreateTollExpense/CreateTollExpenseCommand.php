<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\CreateTollExpense;

final readonly class CreateTollExpenseCommand
{
    public function __construct(
        public string $personId,
        public string $date,
        public float $amount,
        public ?string $departure = null,
        public ?string $arrival = null,
        public ?string $description = null,
    ) {}
}
