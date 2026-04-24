<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\UpdateProfile;

final readonly class UpdateProfileCommand
{
    public function __construct(
        public string $userId,
        public string $email,
        public ?int $defaultYear,
        public ?int $defaultFiscalPower,
    ) {}
}
