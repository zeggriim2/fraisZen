<?php

declare(strict_types=1);

use App\Admin\Application\Command\UpsertBaremeKilometrique\UpsertBaremeKilometriqueCommand;
use App\Admin\Application\Command\UpsertBaremeKilometrique\UpsertBaremeKilometriqueCommandHandler;
use App\Expense\Domain\Entity\BaremeKilometrique;
use App\Expense\Domain\Repository\BaremeKilometriqueRepositoryInterface;

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

it('crée une nouvelle entité quand aucun barème n\'existe pour l\'année', function () use ($rates) {
    $saved = null;
    $repo = new class($saved) implements BaremeKilometriqueRepositoryInterface {
        public function __construct(private ?BaremeKilometrique &$saved) {}
        public function findByYear(int $year): ?BaremeKilometrique { return null; }
        public function findAll(): array { return []; }
        public function save(BaremeKilometrique $b): void { $this->saved = $b; }
    };

    $handler = new UpsertBaremeKilometriqueCommandHandler($repo);
    $handler(new UpsertBaremeKilometriqueCommand(2026, $rates));

    expect($saved)->not->toBeNull();
    expect($saved->year())->toBe(2026);
    expect($saved->rates())->toBe($rates);
});

it('met à jour l\'entité existante sans en créer une nouvelle', function () use ($rates) {
    $oldRates = array_merge($rates, ['electricMultiplier' => 1.0]);
    $existing = new BaremeKilometrique(2024, $oldRates);
    $saved = null;

    $repo = new class($existing, $saved) implements BaremeKilometriqueRepositoryInterface {
        public function __construct(
            private BaremeKilometrique $existing,
            private ?BaremeKilometrique &$saved,
        ) {}
        public function findByYear(int $year): ?BaremeKilometrique { return $this->existing; }
        public function findAll(): array { return [$this->existing]; }
        public function save(BaremeKilometrique $b): void { $this->saved = $b; }
    };

    $handler = new UpsertBaremeKilometriqueCommandHandler($repo);
    $handler(new UpsertBaremeKilometriqueCommand(2024, $rates));

    expect($saved)->toBe($existing);
    expect($saved->rates()['electricMultiplier'])->toBe(1.20);
});

it('appelle toujours save après l\'upsert', function () use ($rates) {
    $saveCount = 0;
    $repo = new class($saveCount) implements BaremeKilometriqueRepositoryInterface {
        public function __construct(private int &$saveCount) {}
        public function findByYear(int $year): ?BaremeKilometrique { return null; }
        public function findAll(): array { return []; }
        public function save(BaremeKilometrique $b): void { ++$this->saveCount; }
    };

    $handler = new UpsertBaremeKilometriqueCommandHandler($repo);
    $handler(new UpsertBaremeKilometriqueCommand(2025, $rates));

    expect($saveCount)->toBe(1);
});