<?php

declare(strict_types=1);

use App\Expense\Domain\Entity\BaremeKilometrique;

$rates = [
    'car' => [
        3 => ['rate1' => 0.529, 'rate2' => 0.316, 'fixed2' => 1065, 'rate3' => 0.370],
        4 => ['rate1' => 0.606, 'rate2' => 0.340, 'fixed2' => 1330, 'rate3' => 0.407],
        5 => ['rate1' => 0.636, 'rate2' => 0.357, 'fixed2' => 1395, 'rate3' => 0.427],
        6 => ['rate1' => 0.665, 'rate2' => 0.374, 'fixed2' => 1457, 'rate3' => 0.447],
        7 => ['rate1' => 0.697, 'rate2' => 0.394, 'fixed2' => 1515, 'rate3' => 0.470],
    ],
    'motorcycle' => [
        1 => ['rate1' => 0.395, 'rate2' => 0.099, 'fixed2' => 891,  'rate3' => 0.248],
        3 => ['rate1' => 0.468, 'rate2' => 0.082, 'fixed2' => 1158, 'rate3' => 0.275],
        6 => ['rate1' => 0.606, 'rate2' => 0.079, 'fixed2' => 1583, 'rate3' => 0.343],
    ],
    'moped' => ['rate1' => 0.315, 'rate2' => 0.079, 'fixed2' => 711, 'rate3' => 0.198],
    'electricMultiplier' => 1.20,
];

it('expose l\'année et les taux passés au constructeur', function () use ($rates) {
    $bareme = new BaremeKilometrique(2025, $rates);

    expect($bareme->year())->toBe(2025);
    expect($bareme->rates())->toBe($rates);
});

it('met à jour les taux via setRates', function () use ($rates) {
    $bareme = new BaremeKilometrique(2024, $rates);
    $newRates = array_merge($rates, ['electricMultiplier' => 1.0]);

    $bareme->setRates($newRates);

    expect($bareme->rates()['electricMultiplier'])->toBe(1.0);
});

it('sérialise correctement en tableau via toArray', function () use ($rates) {
    $bareme = new BaremeKilometrique(2025, $rates);
    $array = $bareme->toArray();

    expect($array['year'])->toBe(2025);
    expect($array['rates'])->toBe($rates);
    expect(array_keys($array))->toBe(['year', 'rates']);
});