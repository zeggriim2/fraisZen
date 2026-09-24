<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\UploadReceiptDocument;

final readonly class UploadReceiptDocumentCommand
{
    public function __construct(public string $personId, public string $sourcePath, public string $originalName, public ?string $mimeType, public int $size)
    {
    }
}
