<?php

declare(strict_types=1);

namespace App\Person\Domain\Exception;

final class PersonNotFoundException extends \DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Person with id "%s" not found.', $id));
    }
}
