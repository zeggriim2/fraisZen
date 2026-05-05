<?php

declare(strict_types=1);

namespace App\Admin\Application\Query\ListBaremeKilometrique;

use App\Expense\Domain\Entity\BaremeKilometrique;
use App\Expense\Domain\Repository\BaremeKilometriqueRepositoryInterface;
use App\SharedKernel\Application\Bus\QueryHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListBaremeKilometriqueQueryHandler implements QueryHandlerInterface
{
    public function __construct(private BaremeKilometriqueRepositoryInterface $repository)
    {
    }

    /** @return list<array{year: int, rates: array}> */
    public function __invoke(ListBaremeKilometriqueQuery $query): array
    {
        return array_values(
            array_map(fn (BaremeKilometrique $b) => $b->toArray(), $this->repository->findAll())
        );
    }
}