<?php

declare(strict_types=1);

use App\Expense\Application\Export\ExportResult;
use App\Expense\Infrastructure\Export\DompdfSummaryPdfExporter;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

function makePdfExporter(): DompdfSummaryPdfExporter
{
    $twig = new Environment(new FilesystemLoader(__DIR__.'/../../../../../templates'));

    return new DompdfSummaryPdfExporter($twig);
}

$data = [
    'travel' => ['trips' => [], 'totalKm' => 0.0, 'deduction' => 0.0],
    'remoteWork' => ['days' => 0, 'dailyAllowance' => 0.0, 'deduction' => 0.0],
    'toll' => ['entries' => 0, 'deduction' => 0.0],
    'meal' => ['entries' => 0, 'homeMealValue' => 0.0, 'deduction' => 0.0],
    'parking' => ['entries' => 0, 'deduction' => 0.0],
    'total' => 0.0,
];

it('returns format pdf', function () {
    expect(makePdfExporter()->format())->toBe('pdf');
});

it('returns ExportResult with pdf mimeType and correct filename', function () use ($data) {
    $result = makePdfExporter()->export($data, 2025);

    expect($result)->toBeInstanceOf(ExportResult::class);
    expect($result->mimeType)->toBe('application/pdf');
    expect($result->filename)->toBe('frais-reels-2025.pdf');
    expect($result->content)->not->toBeEmpty();
});