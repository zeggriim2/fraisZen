<?php

declare(strict_types=1);

namespace App\Expense\Application\Export;

interface SummaryExporterInterface
{
    public function format(): string;

    /** @param array<string, mixed> $data */
    public function export(array $data, int $year): ExportResult;
}
