<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\UploadReceipt;

final readonly class UploadReceiptCommand
{
    public function __construct(
        public string $expenseId,
        public string $sourcePath,
        public string $originalName,
        public ?string $mimeType,
        public int $size,
    ) {
    }
}
