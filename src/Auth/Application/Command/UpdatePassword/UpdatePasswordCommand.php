<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\UpdatePassword;

final readonly class UpdatePasswordCommand
{
    public function __construct(
        public string $userId,
        public string $currentPassword,
        public string $newPassword,
    ) {}
}
