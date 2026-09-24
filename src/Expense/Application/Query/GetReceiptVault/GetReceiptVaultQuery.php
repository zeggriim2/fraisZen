<?php

declare(strict_types=1);

namespace App\Expense\Application\Query\GetReceiptVault;

final readonly class GetReceiptVaultQuery
{
    public function __construct(public string $personId, public int $year, public int $page, public int $pageSize, public string $status, public string $search)
    {
    }
}
