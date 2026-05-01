<?php

declare(strict_types=1);

namespace App\Person\Application\Command\UpdatePerson;

final readonly class UpdatePersonCommand
{
    public function __construct(
        public string $id,
        public string $firstName,
        public string $lastName,
        public ?string $email = null,
        public bool $favorite = false,
    ) {
    }
}
