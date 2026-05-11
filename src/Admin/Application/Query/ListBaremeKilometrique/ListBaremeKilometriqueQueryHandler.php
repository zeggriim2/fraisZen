<?php

declare(strict_types=1);

namespace App\Admin\Application\Query\ListBaremeKilometrique;

use App\Admin\Application\ReadModel\BaremeKilometriqueReadModel;
use App\Expense\Domain\Repository\BaremeKilometriqueRepositoryInterface;
use App\SharedKernel\Application\Bus\QueryHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListBaremeKilometriqueQueryHandler implements QueryHandlerInterface
{
    public function __construct(private BaremeKilometriqueRepositoryInterface $repository)
    {
    }

    /** @return list<BaremeKilometriqueReadModel> */
    public function __invoke(ListBaremeKilometriqueQuery $query): array
    {
        return array_values(
            array_map(
                BaremeKilometriqueReadModel::fromEntity(...),
                $this->repository->findAll(),
            )
        );
    }
}
