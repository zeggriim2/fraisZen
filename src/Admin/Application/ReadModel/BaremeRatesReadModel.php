<?php

declare(strict_types=1);

namespace App\Admin\Application\ReadModel;

final readonly class BaremeRatesReadModel
{
    /**
     * @param array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}> $car
     * @param array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}> $motorcycle
     * @param array{rate1: float, rate2: float, fixed2: int, rate3: float}             $moped
     */
    public function __construct(
        public array $car,
        public array $motorcycle,
        public array $moped,
        public float $electricMultiplier,
    ) {
    }

    /**
     * @param array{car: array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}>, motorcycle: array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}>, moped: array{rate1: float, rate2: float, fixed2: int, rate3: float}, electricMultiplier: float} $rates
     */
    public static function fromArray(array $rates): self
    {
        return new self(
            car: $rates['car'],
            motorcycle: $rates['motorcycle'],
            moped: $rates['moped'],
            electricMultiplier: $rates['electricMultiplier'],
        );
    }
}
