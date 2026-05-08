<?php

declare(strict_types=1);

namespace App\Expense\Application\Export;

final class SummaryExporterRegistry
{
    /** @param iterable<SummaryExporterInterface> $exporters */
    public function __construct(private iterable $exporters)
    {
    }

    public function get(string $format): SummaryExporterInterface
    {
        foreach ($this->exporters as $exporter) {
            if ($exporter->format() === $format) {
                return $exporter;
            }
        }

        throw new \InvalidArgumentException("No exporter for format '$format'");
    }
}