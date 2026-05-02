<?php

declare(strict_types=1);

namespace App\Expense\Application\Export;

use Symfony\Component\HttpFoundation\StreamedResponse;

interface SummaryCsvExporterInterface
{
    /** @param array<string, mixed> $data */
    public function export(array $data, int $year): StreamedResponse;
}
