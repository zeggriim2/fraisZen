<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\CreateMealExpense;

final readonly class CreateMealExpenseCommand
{
    public function __construct(
        public string $personId,
        public string $date,
        public float $mealAmount,
        public ?string $description,
        public float $homeMealValue = 5.35,
        public float $employerTicketContribution = 0.0,
        public bool $withoutReceipt = false,
    ) {}
}
