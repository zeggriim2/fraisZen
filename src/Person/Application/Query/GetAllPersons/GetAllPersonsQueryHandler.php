<?php

declare(strict_types=1);

namespace App\Person\Application\Query\GetAllPersons;

use App\Person\Domain\Repository\PersonRepositoryInterface;
use App\SharedKernel\Application\Bus\QueryHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetAllPersonsQueryHandler implements QueryHandlerInterface
{
    public function __construct(private PersonRepositoryInterface $repository)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function __invoke(GetAllPersonsQuery $query): array
    {
        return array_map(fn ($p) => $p->toArray(), $this->repository->findAllByUserId($query->userId));
    }
}
