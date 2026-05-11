<?php

declare(strict_types=1);

use App\Expense\Application\Query\GetBaremeKilometriqueQuery\GetBaremeKilometriqueQuery;
use App\Expense\Application\Query\GetBaremeKilometriqueQuery\GetBaremeKilometriqueQueryHandler;
use App\Expense\Domain\Entity\BaremeKilometrique;
use App\Expense\Domain\Exception\BaremeKilometriqueNotFoundException;
use App\Expense\Domain\Repository\BaremeKilometriqueRepositoryInterface;

$rates2024 = [
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

it('retourne les taux de l\'entité en base quand l\'année existe', function () use ($rates2024) {
    $entity = new BaremeKilometrique(2024, $rates2024);

    $repo = new class($entity) implements BaremeKilometriqueRepositoryInterface {
        public function __construct(private BaremeKilometrique $entity) {}
        public function findByYear(int $year): ?BaremeKilometrique { return $this->entity; }
        public function findAll(): array { return [$this->entity]; }
        public function save(BaremeKilometrique $b): void {}
    };

    $handler = new GetBaremeKilometriqueQueryHandler($repo);
    $result = $handler(new GetBaremeKilometriqueQuery(2024));

    expect($result)->toBe($rates2024);
});

it('lève une BaremeKilometriqueNotFoundException quand l\'année est absente en base', function () {
    $repo = new class implements BaremeKilometriqueRepositoryInterface {
        public function findByYear(int $year): ?BaremeKilometrique { return null; }
        public function findAll(): array { return []; }
        public function save(BaremeKilometrique $b): void {}
    };

    $handler = new GetBaremeKilometriqueQueryHandler($repo);

    expect(fn () => $handler(new GetBaremeKilometriqueQuery(2023)))
        ->toThrow(BaremeKilometriqueNotFoundException::class);
});

it('le message d\'exception contient l\'année manquante', function () {
    $repo = new class implements BaremeKilometriqueRepositoryInterface {
        public function findByYear(int $year): ?BaremeKilometrique { return null; }
        public function findAll(): array { return []; }
        public function save(BaremeKilometrique $b): void {}
    };

    $handler = new GetBaremeKilometriqueQueryHandler($repo);

    expect(fn () => $handler(new GetBaremeKilometriqueQuery(2099)))
        ->toThrow(BaremeKilometriqueNotFoundException::class, '2099');
});

it('préfère les données en base plutôt que le provider statique', function () use ($rates2024) {
    $customRates = array_merge($rates2024, ['electricMultiplier' => 1.50]);
    $entity = new BaremeKilometrique(2025, $customRates);

    $repo = new class($entity) implements BaremeKilometriqueRepositoryInterface {
        public function __construct(private BaremeKilometrique $entity) {}
        public function findByYear(int $year): ?BaremeKilometrique { return $this->entity; }
        public function findAll(): array { return [$this->entity]; }
        public function save(BaremeKilometrique $b): void {}
    };

    $handler = new GetBaremeKilometriqueQueryHandler($repo);
    $result = $handler(new GetBaremeKilometriqueQuery(2025));

    expect($result['electricMultiplier'])->toBe(1.50);
});
