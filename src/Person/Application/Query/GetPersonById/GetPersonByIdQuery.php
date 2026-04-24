<?php

declare(strict_types=1);

namespace App\Person\Application\Query\GetPersonById;

final readonly class GetPersonByIdQuery
{
    public function __construct(public string $id) {}
}
