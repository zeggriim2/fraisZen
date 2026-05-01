<?php

declare(strict_types=1);

namespace App\Expense\Application\Query\GetExpensesByPeriod;

use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\SharedKernel\Application\Bus\QueryHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetExpensesByPeriodQueryHandler implements QueryHandlerInterface
{
    public function __construct(private ExpenseRepositoryInterface $repository)
    {
    }

    public function __invoke(GetExpensesByPeriodQuery $query): array
    {
        $from = new \DateTimeImmutable($query->from);
        $to = new \DateTimeImmutable($query->to);

        $expenses = null !== $query->personId
            ? $this->repository->findByPersonAndPeriod($query->personId, $from, $to)
            : $this->repository->findByPeriod($from, $to);

        return array_map(fn ($e) => $e->toArray(), $expenses);
    }
}
