<?php

declare(strict_types=1);

namespace App\Person\Application\Command\DeletePerson;

final readonly class DeletePersonCommand
{
    public function __construct(public string $id) {}
}
