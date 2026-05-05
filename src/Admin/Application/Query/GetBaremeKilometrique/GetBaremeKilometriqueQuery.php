<?php

declare(strict_types=1);

namespace App\Admin\Application\Query\GetBaremeKilometrique;

final readonly class GetBaremeKilometriqueQuery
{
    public function __construct(public readonly int $year)
    {
    }
}