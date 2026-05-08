<?php

declare(strict_types=1);

namespace App\Expense\Application\Export;

final readonly class ExportResult
{
    public function __construct(
        public string $content,
        public string $mimeType,
        public string $filename,
    ) {
    }
}