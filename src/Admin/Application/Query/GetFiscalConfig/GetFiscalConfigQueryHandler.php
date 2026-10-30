<?php

declare(strict_types=1);

namespace App\Admin\Application\Query\GetFiscalConfig;

use App\Admin\Domain\Repository\FiscalConfigRepositoryInterface;
use App\SharedKernel\Application\Bus\QueryHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetFiscalConfigQueryHandler implements QueryHandlerInterface
{
    public function __construct(private FiscalConfigRepositoryInterface $repository)
    {
    }

    /** @return array<string, mixed>|null see FiscalConfig::toArray() */
    public function __invoke(GetFiscalConfigQuery $query): ?array
    {
        return $this->repository->findByYear($query->year)?->toArray();
    }
}
