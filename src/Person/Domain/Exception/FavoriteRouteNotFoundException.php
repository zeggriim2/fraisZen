<?php

declare(strict_types=1);

namespace App\Person\Domain\Exception;

final class FavoriteRouteNotFoundException extends \DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('FavoriteRoute with id "%s" not found.', $id));
    }
}
