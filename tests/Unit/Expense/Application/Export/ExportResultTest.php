<?php

declare(strict_types=1);

use App\Expense\Application\Export\ExportResult;

it('exposes content, mimeType and filename', function () {
    $result = new ExportResult('raw-bytes', 'application/pdf', 'frais-reels-2025.pdf');

    expect($result->content)->toBe('raw-bytes');
    expect($result->mimeType)->toBe('application/pdf');
    expect($result->filename)->toBe('frais-reels-2025.pdf');
});