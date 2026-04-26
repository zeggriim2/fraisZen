<?php

declare(strict_types=1);

namespace App\Person\Application\Command\CreatePerson;

final readonly class CreatePersonCommand
{
    public function __construct(
        public string $userId,
        public string $firstName,
        public string $lastName,
        public ?string $email = null,
        public bool $favorite = false,
    ) {}
}
