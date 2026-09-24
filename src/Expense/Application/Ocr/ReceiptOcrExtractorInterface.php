<?php

declare(strict_types=1);

namespace App\Expense\Application\Ocr;

interface ReceiptOcrExtractorInterface
{
    /** @return array{status: string, merchant: ?string, date: ?string, amount: ?float, rawText: string} */
    public function extract(string $path, string $mimeType): array;
}
