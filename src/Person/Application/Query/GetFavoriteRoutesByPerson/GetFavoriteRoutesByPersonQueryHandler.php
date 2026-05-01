<?php

declare(strict_types=1);

namespace App\Person\Application\Query\GetFavoriteRoutesByPerson;

use App\Person\Domain\Repository\FavoriteRouteRepositoryInterface;
use App\SharedKernel\Application\Bus\QueryHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetFavoriteRoutesByPersonQueryHandler implements QueryHandlerInterface
{
    public function __construct(private FavoriteRouteRepositoryInterface $repository)
    {
    }

    public function __invoke(GetFavoriteRoutesByPersonQuery $query): array
    {
        return array_map(fn ($r) => $r->toArray(), $this->repository->findByPersonId($query->personId));
    }
}
