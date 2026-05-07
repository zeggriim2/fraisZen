<?php

declare(strict_types=1);

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

function makeTwig(): Environment
{
    $loader = new FilesystemLoader(__DIR__.'/../../../../../templates');

    return new Environment($loader, ['strict_variables' => true]);
}

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

it('renders the summary PDF template with year and total', function () use ($data) {
    $twig = makeTwig();
    $html = $twig->render('expense/summary_pdf.html.twig', ['data' => $data, 'year' => 2025]);

    expect($html)->toContain('2025');
    expect($html)->toContain('415');
});

it('renders trip rows in the template', function () use ($data) {
    $twig = makeTwig();
    $html = $twig->render('expense/summary_pdf.html.twig', ['data' => $data, 'year' => 2025]);

    expect($html)->toContain('Paris');
    expect($html)->toContain('Lyon');
    expect($html)->toContain('450');
});

it('renders meal and parking sections when entries exist', function () use ($data) {
    $twig = makeTwig();
    $html = $twig->render('expense/summary_pdf.html.twig', ['data' => $data, 'year' => 2025]);

    expect($html)->toContain('Repas');
    expect($html)->toContain('Parking');
});