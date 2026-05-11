<?php

declare(strict_types=1);

namespace App\Admin\Application\Query\GetBaremeKilometrique;

use App\Admin\Application\ReadModel\BaremeKilometriqueReadModel;
use App\Expense\Domain\Repository\BaremeKilometriqueRepositoryInterface;
use App\SharedKernel\Application\Bus\QueryHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetBaremeKilometriqueQueryHandler implements QueryHandlerInterface
{
    public function __construct(private BaremeKilometriqueRepositoryInterface $repository)
    {
    }

    public function __invoke(GetBaremeKilometriqueQuery $query): ?BaremeKilometriqueReadModel
    {
        $entity = $this->repository->findByYear($query->year);

        return null !== $entity ? BaremeKilometriqueReadModel::fromEntity($entity) : null;
    }
}
