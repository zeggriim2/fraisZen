<?php

declare(strict_types=1);

namespace App\Expense\Application\Query\GetDistance;

final readonly class GetDistanceQuery
{
    public function __construct(
        public float $fromLat,
        public float $fromLng,
        public float $toLat,
        public float $toLng,
    ) {
    }
}