<?php

declare(strict_types=1);

namespace App\Person\Application\Query\GetFavoriteRoutesByPerson;

final readonly class GetFavoriteRoutesByPersonQuery
{
    public function __construct(public string $personId) {}
}
