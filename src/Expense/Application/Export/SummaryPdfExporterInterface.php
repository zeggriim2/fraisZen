<?php

declare(strict_types=1);

namespace App\Expense\Application\Export;

use Symfony\Component\HttpFoundation\Response;

interface SummaryPdfExporterInterface
{
    /** @param array<string, mixed> $data */
    public function export(array $data, int $year): Response;
}
