<?php

declare(strict_types=1);

namespace App\Admin\Application\Command\UpsertBaremeKilometrique;

final readonly class UpsertBaremeKilometriqueCommand
{
    /**
     * @param array{car: array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}>, motorcycle: array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}>, moped: array{rate1: float, rate2: float, fixed2: int, rate3: float}, electricMultiplier: float} $rates
     */
    public function __construct(
        public int $year,
        public array $rates,
    ) {
    }
}
