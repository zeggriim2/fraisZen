<?php

declare(strict_types=1);

namespace App\Person\Application\Query\GetAllPersons;

final readonly class GetAllPersonsQuery
{
    public function __construct(public string $userId)
    {
    }
}
