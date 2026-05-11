<?php

declare(strict_types=1);

namespace App\Expense\Application\Query\GetBaremeKilometriqueQuery;

use App\Expense\Domain\Exception\BaremeKilometriqueNotFoundException;
use App\Expense\Domain\Repository\BaremeKilometriqueRepositoryInterface;
use App\SharedKernel\Application\Bus\QueryHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetBaremeKilometriqueQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private BaremeKilometriqueRepositoryInterface $kilometriqueRepository,
    ) {
    }

    /**
     * @return array{car: array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}>, motorcycle: array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}>, moped: array{rate1: float, rate2: float, fixed2: int, rate3: float}, electricMultiplier: float}
     */
    public function __invoke(GetBaremeKilometriqueQuery $query): array
    {
        $entity = $this->kilometriqueRepository->findByYear($query->year);

        if (null === $entity) {
            throw BaremeKilometriqueNotFoundException::forYear($query->year);
        }

        return $entity->rates();
    }
}
