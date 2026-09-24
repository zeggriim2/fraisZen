<?php

declare(strict_types=1);

namespace App\Expense\Application\Receipt;

interface ReceiptDocumentParserInterface
{
    public function parse(string $path, string $mimeType): ParsedReceiptDocument;
}
