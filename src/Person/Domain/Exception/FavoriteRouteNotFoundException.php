<?php

declare(strict_types=1);

namespace App\Person\Domain\Exception;

use App\SharedKernel\Domain\Exception\NotFoundException;

final class FavoriteRouteNotFoundException extends NotFoundException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('FavoriteRoute with id "%s" not found.', $id));
    }
}
