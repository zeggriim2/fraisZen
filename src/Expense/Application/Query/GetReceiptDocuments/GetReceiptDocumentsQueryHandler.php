<?php

declare(strict_types=1);

namespace App\Expense\Application\Query\GetReceiptDocuments;

use App\Expense\Domain\Repository\ReceiptDocumentRepositoryInterface;
use App\SharedKernel\Application\Bus\QueryHandlerInterface;

final readonly class GetReceiptDocumentsQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private ReceiptDocumentRepositoryInterface $repository,
    ) {
    }

    /** @return array<string, mixed> */
    public function __invoke(GetReceiptDocumentsQuery $query): array
    {
        $page = max(1, $query->page);
        $size = min(24, max(1, $query->pageSize));
        $total = $this->repository->count($query->personId, $query->year);
        $items = array_map(fn ($d) => $d->toArray(), $this->repository->findPage($query->personId, $query->year, ($page - 1) * $size, $size));

        return ['total' => $total, 'page' => $page, 'pageSize' => $size, 'hasMore' => $page * $size < $total, 'items' => $items];
    }
}
