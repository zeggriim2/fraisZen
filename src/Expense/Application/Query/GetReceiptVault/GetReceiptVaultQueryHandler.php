<?php

declare(strict_types=1);

namespace App\Expense\Application\Query\GetReceiptVault;

use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\SharedKernel\Application\Bus\QueryHandlerInterface;

final readonly class GetReceiptVaultQueryHandler implements QueryHandlerInterface
{
    public function __construct(private ExpenseRepositoryInterface $repository)
    {
    }

    /** @return array<string, mixed> */
    public function __invoke(GetReceiptVaultQuery $query): array
    {
        $page = max(1, $query->page);
        $pageSize = min(24, max(1, $query->pageSize));
        $status = in_array($query->status, ['all', 'missing', 'archived'], true) ? $query->status : 'all';
        $search = mb_substr($query->search, 0, 100);
        $expenses = $this->repository->findReceiptPage($query->personId, $query->year, ($page - 1) * $pageSize, $pageSize, $status, $search);
        $items = array_map(static function ($expense): array {
            $data = $expense->toArray();

            return array_intersect_key($data, array_flip(['id', 'date', 'type', 'typeLabel', 'description', 'amount', 'receiptFilename', 'receiptMimeType', 'receiptOcrData', 'receiptUploadedAt']));
        }, $expenses);
        $counts = $this->repository->receiptVaultCounts($query->personId, $query->year, $status, $search);

        return ['personId' => $query->personId, 'year' => $query->year, ...$counts, 'page' => $page, 'pageSize' => $pageSize, 'hasMore' => $page * $pageSize < $counts['filtered'], 'items' => $items];
    }
}
