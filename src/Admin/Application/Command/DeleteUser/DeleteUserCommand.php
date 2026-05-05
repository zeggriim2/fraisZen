<?php

declare(strict_types=1);

namespace App\Admin\Application\Command\DeleteUser;

final readonly class DeleteUserCommand
{
    public function __construct(public string $userId)
    {
    }
}
