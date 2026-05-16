<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\RequestPasswordReset;

final readonly class RequestPasswordResetCommand
{
    public function __construct(
        public string $email,
    ) {
    }
}
