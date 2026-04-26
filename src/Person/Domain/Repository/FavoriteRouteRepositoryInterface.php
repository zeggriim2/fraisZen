<?php

declare(strict_types=1);

namespace App\Person\Domain\Repository;

use App\Person\Domain\Entity\FavoriteRoute;
use App\Person\Domain\ValueObject\FavoriteRouteId;

interface FavoriteRouteRepositoryInterface
{
    public function save(FavoriteRoute $route): void;

    public function delete(FavoriteRoute $route): void;

    public function findById(FavoriteRouteId $id): ?FavoriteRoute;

    /** @return FavoriteRoute[] */
    public function findByPersonId(string $personId): array;
}
