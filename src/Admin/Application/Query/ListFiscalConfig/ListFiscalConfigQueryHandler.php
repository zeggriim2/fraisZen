<?php

declare(strict_types=1);

namespace App\Admin\Application\Query\ListFiscalConfig;

use App\Admin\Domain\Entity\FiscalConfig;
use App\Admin\Domain\Repository\FiscalConfigRepositoryInterface;
use App\SharedKernel\Application\Bus\QueryHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListFiscalConfigQueryHandler implements QueryHandlerInterface
{
    public function __construct(private FiscalConfigRepositoryInterface $repository)
    {
    }

    /** @return list<array{year: int, remoteWorkDailyAllowance: float, homeMealValue: float}> */
    public function __invoke(ListFiscalConfigQuery $query): array
    {
        return array_map(fn (FiscalConfig $c) => $c->toArray(), $this->repository->findAll());
    }
}
