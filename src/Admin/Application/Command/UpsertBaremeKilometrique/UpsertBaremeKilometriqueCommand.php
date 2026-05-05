<?php

declare(strict_types=1);

namespace App\Admin\Application\Command\UpsertBaremeKilometrique;

final readonly class UpsertBaremeKilometriqueCommand
{
    public function __construct(
        public readonly int $year,
        public readonly array $rates,
    ) {
    }
}