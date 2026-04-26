<?php

declare(strict_types=1);

namespace App\Person\Application\Command\UpdateFavoriteRoute;

final readonly class UpdateFavoriteRouteCommand
{
    public function __construct(
        public string $id,
        public string $name,
        public string $departure,
        public string $arrival,
        public string $vehicleType,
        public ?int $vehiclePower,
        public bool $isElectric,
        public bool $roundTrip,
    ) {}
}
