<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\CreateParkingExpense;

final readonly class CreateParkingExpenseCommand
{
    public function __construct(
        public string $personId,
        public string $date,
        public float $amount,
        public ?string $location,
        public ?string $description,
    ) {
    }
}
