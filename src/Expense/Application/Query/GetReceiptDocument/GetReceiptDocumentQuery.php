<?php

declare(strict_types=1);

namespace App\Expense\Application\Query\GetReceiptDocument;

final readonly class GetReceiptDocumentQuery
{
    public function __construct(public string $documentId, public string $personId)
    {
    }
}
