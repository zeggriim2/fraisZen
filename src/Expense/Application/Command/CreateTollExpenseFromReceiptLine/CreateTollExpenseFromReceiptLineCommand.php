<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\CreateTollExpenseFromReceiptLine;

final readonly class CreateTollExpenseFromReceiptLineCommand
{
    public function __construct(
        public string $documentId,
        public string $lineId,
        public string $personId,
    ) {
    }
}
