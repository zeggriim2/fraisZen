<?php

declare(strict_types=1);

namespace App\Person\Application\Command\DeleteFavoriteRoute;

final readonly class DeleteFavoriteRouteCommand
{
    public function __construct(public string $id)
    {
    }
}
