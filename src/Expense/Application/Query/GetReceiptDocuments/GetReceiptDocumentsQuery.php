<?php

declare(strict_types=1);

namespace App\Expense\Application\Query\GetReceiptDocuments;

final readonly class GetReceiptDocumentsQuery
{
    public function __construct(public string $personId, public int $year, public int $page = 1, public int $pageSize = 6)
    {
    }
}
