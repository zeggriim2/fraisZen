<?php

declare(strict_types=1);

use App\Expense\Application\Export\ExportResult;
use App\Expense\Application\Export\SummaryExporterInterface;
use App\Expense\Application\Export\SummaryExporterRegistry;

function makeExporter(string $format): SummaryExporterInterface
{
    return new class($format) implements SummaryExporterInterface {
        public function __construct(private string $fmt) {}

        public function format(): string { return $this->fmt; }

        public function export(array $data, int $year): ExportResult
        {
            return new ExportResult('', 'text/plain', "file-$year.txt");
        }
    };
}

it('returns the exporter matching the requested format', function () {
    $registry = new SummaryExporterRegistry([
        makeExporter('pdf'),
        makeExporter('csv'),
    ]);

    expect($registry->get('pdf')->format())->toBe('pdf');
    expect($registry->get('csv')->format())->toBe('csv');
});

it('throws InvalidArgumentException when no exporter matches', function () {
    $registry = new SummaryExporterRegistry([makeExporter('pdf')]);

    expect(fn () => $registry->get('xlsx'))
        ->toThrow(\InvalidArgumentException::class, "No exporter for format 'xlsx'");
});
