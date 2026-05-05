<?php

declare(strict_types=1);

namespace App\Admin\Application\Command\UpdateUserSubscriptionStatus;

final readonly class UpdateUserSubscriptionStatusCommand
{
    public function __construct(
        public string $userId,
        public string $status,
    ) {
    }
}
