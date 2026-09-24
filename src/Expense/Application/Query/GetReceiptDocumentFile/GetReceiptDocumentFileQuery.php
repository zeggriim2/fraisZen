<?php

declare(strict_types=1);

namespace App\Expense\Application\Query\GetReceiptDocumentFile;

final readonly class GetReceiptDocumentFileQuery
{
    public function __construct(public string $documentId, public string $personId)
    {
    }
}
