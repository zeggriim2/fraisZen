<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\DeleteReceiptDocument;

final readonly class DeleteReceiptDocumentCommand
{
    public function __construct(public string $documentId, public string $personId)
    {
    }
}
