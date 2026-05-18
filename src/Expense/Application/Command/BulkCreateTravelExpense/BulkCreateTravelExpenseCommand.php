<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\BulkCreateTravelExpense;

final readonly class BulkCreateTravelExpenseCommand
{
    /**
     * @param string[] $dates ISO 8601 date strings (Y-m-d), already filtered by caller (no weekends, no holidays)
     */
    public function __construct(
        public string $personId,
        public array $dates,
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
