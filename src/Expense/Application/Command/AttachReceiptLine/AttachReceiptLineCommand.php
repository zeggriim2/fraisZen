<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\AttachReceiptLine;

final readonly class AttachReceiptLineCommand
{
    public function __construct(
        public string $documentId,
        public string $lineId,
        public string $expenseId,
        public string $personId,
    ) {
    }
}
