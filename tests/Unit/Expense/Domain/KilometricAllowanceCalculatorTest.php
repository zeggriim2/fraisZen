<?php

declare(strict_types=1);

use App\Expense\Domain\Entity\BaremeKilometrique;
use App\Expense\Domain\Repository\BaremeKilometriqueRepositoryInterface;
use App\Expense\Domain\Service\KilometricAllowanceCalculator;

// Stub : retourne toujours null → fallback sur BaremeKilometriqueProvider (PHP hardcodé)
$stubRepo = new class implements BaremeKilometriqueRepositoryInterface {
    public function findByYear(int $year): ?BaremeKilometrique { return null; }
    public function findAll(): array { return []; }
    public function save(BaremeKilometrique $bareme): void {}
};

$calc = new KilometricAllowanceCalculator($stubRepo);

// Barème 2025 (identique 2023) — voiture 5 CV, tranche 1 : 0.636 €/km

it('calcule le montant tranche 1 pour voiture 5CV (< 5000 km)', function () use ($calc) {
    // 100 km × 0.636 = 63.60 €
    $result = $calc->calculateForPowerAndDistance(5, 100.0, 2025);

    expect($result)->toBe(63.6);
});

it('calcule le montant tranche 2 pour voiture 5CV (entre 5000 et 20000 km)', function () use ($calc) {
    // 10 000 km × 0.357 + 1395 = 3570 + 1395 = 4965.00 €
    $result = $calc->calculateForPowerAndDistance(5, 10_000.0, 2025);

    expect($result)->toBe(4965.0);
});

it('calcule le montant tranche 3 pour voiture 5CV (> 20000 km)', function () use ($calc) {
    // 25 000 km × 0.427 = 10 675.00 €
    $result = $calc->calculateForPowerAndDistance(5, 25_000.0, 2025);

    expect($result)->toBe(10_675.0);
});

it('plafonne la puissance fiscale à 7 CV', function () use ($calc) {
    $result7 = $calc->calculateForPowerAndDistance(7, 1000.0, 2025);
    $result9 = $calc->calculateForPowerAndDistance(9, 1000.0, 2025);

    expect($result7)->toBe($result9);
});

it('plancher la puissance fiscale à 3 CV', function () use ($calc) {
    $result3 = $calc->calculateForPowerAndDistance(3, 1000.0, 2025);
    $result1 = $calc->calculateForPowerAndDistance(1, 1000.0, 2025);

    expect($result3)->toBe($result1);
});

it('applique la majoration de 20% pour les véhicules électriques', function () use ($calc) {
    $trips = [
        ['distanceKm' => 1000.0, 'vehiclePower' => 5, 'vehicleType' => 'car', 'isElectric' => false],
    ];
    $tripElec = [
        ['distanceKm' => 1000.0, 'vehiclePower' => 5, 'vehicleType' => 'car', 'isElectric' => true],
    ];

    $base = $calc->calculateAnnualDeduction($trips, 2025);
    $elec = $calc->calculateAnnualDeduction($tripElec, 2025);

    expect($elec)->toBe(round($base * 1.20, 2));
});

it('regroupe les trajets par véhicule pour le cumul annuel', function () use ($calc) {
    // 2 trajets de 3000 km = 6000 km au total → tranche 2
    $trips = [
        ['distanceKm' => 3000.0, 'vehiclePower' => 5, 'vehicleType' => 'car', 'isElectric' => false],
        ['distanceKm' => 3000.0, 'vehiclePower' => 5, 'vehicleType' => 'car', 'isElectric' => false],
    ];

    $grouped = $calc->calculateAnnualDeduction($trips, 2025);
    // Si calculé séparément : 2 × (3000 × 0.636) = 3816.00 €
    $separate = 2 * $calc->calculateForPowerAndDistance(5, 3000.0, 2025);

    // Le résultat groupé doit être différent du calcul séparé (effet tranche 2)
    // 6000 km × 0.357 + 1395 = 2142 + 1395 = 3537.00 €
    expect($grouped)->toBe(3537.0);
    expect($grouped)->not->toBe($separate);
});

it('renvoie 0 pour un tableau de trajets vide', function () use ($calc) {
    expect($calc->calculateAnnualDeduction([], 2025))->toBe(0.0);
});

it('utilise le barème 2023 pour une année antérieure inconnue', function () use ($calc) {
    $result2023 = $calc->calculateForPowerAndDistance(5, 1000.0, 2023);
    $result2019 = $calc->calculateForPowerAndDistance(5, 1000.0, 2019);

    expect($result2023)->toBe($result2019);
});

it('utilise le barème de la BDD quand disponible', function () {
    $dbRates = [
        'car' => [
            3 => ['rate1' => 0.999, 'rate2' => 0.999, 'fixed2' => 999, 'rate3' => 0.999],
            4 => ['rate1' => 0.999, 'rate2' => 0.999, 'fixed2' => 999, 'rate3' => 0.999],
            5 => ['rate1' => 1.0,   'rate2' => 0.999, 'fixed2' => 999, 'rate3' => 0.999],
            6 => ['rate1' => 0.999, 'rate2' => 0.999, 'fixed2' => 999, 'rate3' => 0.999],
            7 => ['rate1' => 0.999, 'rate2' => 0.999, 'fixed2' => 999, 'rate3' => 0.999],
        ],
        'motorcycle' => [
            1 => ['rate1' => 0.999, 'rate2' => 0.999, 'fixed2' => 999, 'rate3' => 0.999],
            3 => ['rate1' => 0.999, 'rate2' => 0.999, 'fixed2' => 999, 'rate3' => 0.999],
            6 => ['rate1' => 0.999, 'rate2' => 0.999, 'fixed2' => 999, 'rate3' => 0.999],
        ],
        'moped' => ['rate1' => 0.999, 'rate2' => 0.999, 'fixed2' => 999, 'rate3' => 0.999],
        'electricMultiplier' => 1.0,
    ];
    $entity = new BaremeKilometrique(2026, $dbRates);
    $dbRepo = new class($entity) implements BaremeKilometriqueRepositoryInterface {
        public function __construct(private BaremeKilometrique $e) {}
        public function findByYear(int $year): ?BaremeKilometrique { return $this->e; }
        public function findAll(): array { return [$this->e]; }
        public function save(BaremeKilometrique $b): void {}
    };
    $calc = new KilometricAllowanceCalculator($dbRepo);
    // 100 km × 1.0 = 100.0 (taux custom de la BDD, pas le fallback PHP)
    expect($calc->calculateForPowerAndDistance(5, 100.0, 2026))->toBe(100.0);
});