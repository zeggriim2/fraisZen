<?php

declare(strict_types=1);

use App\Expense\Application\Export\ExportResult;
use App\Expense\Infrastructure\Export\CsvSummaryExporter;

$data = [
    'travel' => [
        'trips' => [
            [
                'date' => '2025-03-15',
                'departure' => 'Paris',
                'arrival' => 'Lyon',
                'description' => null,
                'roundTrip' => true,
                'distanceKm' => 450,
                'vehiclePower' => 5,
            ],
        ],
        'totalKm' => 450.0,
        'deduction' => 286.20,
    ],
    'remoteWork' => ['days' => 10, 'dailyAllowance' => 2.50, 'deduction' => 25.0],
    'toll' => ['entries' => 3, 'deduction' => 18.90],
    'meal' => ['entries' => 5, 'homeMealValue' => 4.15, 'deduction' => 73.70],
    'parking' => ['entries' => 2, 'deduction' => 12.0],
    'total' => 415.80,
];

it('returns format csv', function () {
    expect((new CsvSummaryExporter())->format())->toBe('csv');
});

it('returns ExportResult with csv mimeType and correct filename', function () use ($data) {
    $result = (new CsvSummaryExporter())->export($data, 2025);

    expect($result)->toBeInstanceOf(ExportResult::class);
    expect($result->mimeType)->toBe('text/csv; charset=UTF-8');
    expect($result->filename)->toBe('frais-reels-2025.csv');
});

it('includes year and trip data in the csv content', function () use ($data) {
    $result = (new CsvSummaryExporter())->export($data, 2025);

    expect($result->content)->toContain('2025');
    expect($result->content)->toContain('Paris');
    expect($result->content)->toContain('Lyon');
});
