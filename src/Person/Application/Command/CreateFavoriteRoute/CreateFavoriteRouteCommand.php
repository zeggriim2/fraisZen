<?php

declare(strict_types=1);

namespace App\Person\Application\Command\CreateFavoriteRoute;

final readonly class CreateFavoriteRouteCommand
{
    public function __construct(
        public string $personId,
        public string $name,
        public string $departure,
        public string $arrival,
        public string $vehicleType,
        public ?int $vehiclePower,
        public bool $isElectric,
        public bool $roundTrip,
    ) {
    }
}
