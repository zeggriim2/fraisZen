<?php

declare(strict_types=1);

namespace App\Auth\Domain\Exception;

use App\SharedKernel\Domain\Exception\ConflictException;

final class UserAlreadyExistsException extends ConflictException
{
    public static function withEmail(string $email): self
    {
        return new self(sprintf('A user with email "%s" already exists.', $email));
    }
}
