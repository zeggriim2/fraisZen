<?php

declare(strict_types=1);

namespace App\Auth\Domain\Exception;

use App\SharedKernel\Domain\Exception\NotFoundException;

final class UserNotFoundException extends NotFoundException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('User with id "%s" not found.', $id));
    }
}
