<?php

declare(strict_types=1);

namespace App\Auth\Domain\Exception;

final class UserNotFoundException extends \DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('User with id "%s" not found.', $id));
    }
}
