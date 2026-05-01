<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\CreateTravelExpense;

final readonly class CreateTravelExpenseCommand
{
    public function __construct(
        public string $personId,
        public string $date,
        public float $distanceKm,
        public ?int $vehiclePower = null,
        public ?string $departure = null,
        public ?string $arrival = null,
        public ?string $description = null,
        public bool $roundTrip = false,
        public string $vehicleType = 'car',
        public bool $isElectric = false,
    ) {
    }
}
