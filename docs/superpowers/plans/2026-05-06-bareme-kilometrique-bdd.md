# Barème Kilométrique en BDD — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stocker les barèmes kilométriques (2023–2026) en base de données dans une table `bareme_kilometrique`, et exposer un CRUD admin complet (API + frontend Vue) pour les éditer.

**Architecture:** L'entité `BaremeKilometrique` et son repository vivent dans le `Expense` BC (consommateur). Les contrôleurs CRUD vivent dans `Admin` BC et utilisent ce repository. `KilometricAllowanceCalculator` devient injectable : lit la BDD en priorité, fallback sur `BaremeKilometriqueProvider` (PHP hardcodé) si aucune entrée n'existe.

**Tech Stack:** PHP 8.5 / Symfony 8 / Doctrine ORM 3.6 / Pest PHP / Vue 3 / TypeScript / Tailwind CSS / Axios

---

## File Map

### Nouveaux fichiers

**Expense BC — Domain:**
- `src/Expense/Domain/Entity/BaremeKilometrique.php`
- `src/Expense/Domain/Repository/BaremeKilometriqueRepositoryInterface.php`

**Expense BC — Infrastructure:**
- `src/Expense/Infrastructure/Persistence/DoctrineBaremeKilometriqueRepository.php`

**Admin BC — Application:**
- `src/Admin/Application/Query/GetBaremeKilometrique/GetBaremeKilometriqueQuery.php`
- `src/Admin/Application/Query/GetBaremeKilometrique/GetBaremeKilometriqueQueryHandler.php`
- `src/Admin/Application/Query/ListBaremeKilometrique/ListBaremeKilometriqueQuery.php`
- `src/Admin/Application/Query/ListBaremeKilometrique/ListBaremeKilometriqueQueryHandler.php`
- `src/Admin/Application/Command/UpsertBaremeKilometrique/UpsertBaremeKilometriqueCommand.php`
- `src/Admin/Application/Command/UpsertBaremeKilometrique/UpsertBaremeKilometriqueCommandHandler.php`

**Admin BC — Infrastructure:**
- `src/Admin/Infrastructure/Http/AdminBareme/GetController.php`
- `src/Admin/Infrastructure/Http/AdminBareme/ListController.php`
- `src/Admin/Infrastructure/Http/AdminBareme/UpsertController.php`

**Migration:**
- `migrations/Version20260506000001.php`

**Frontend:**
- `frontend/src/views/admin/AdminBaremeKilometriqueView.vue`

### Fichiers modifiés

- `src/Expense/Domain/Service/KilometricAllowanceCalculator.php` — injecter le repository, ajouter `resolveBareme()`
- `tests/Unit/Expense/Domain/KilometricAllowanceCalculatorTest.php` — passer un stub du repository
- `config/routes/api/admin.yaml` — enregistrer les contrôleurs barème
- `frontend/src/api/adminApi.ts` — interfaces TypeScript + méthodes API
- `frontend/src/router/index.ts` — ajouter la route `/admin/bareme-kilometrique`
- `frontend/src/App.vue` — ajouter le lien dans `adminNav`

---

## Task 1 — Entité + Interface Repository

**Files:**
- Create: `src/Expense/Domain/Entity/BaremeKilometrique.php`
- Create: `src/Expense/Domain/Repository/BaremeKilometriqueRepositoryInterface.php`

- [ ] Créer `src/Expense/Domain/Entity/BaremeKilometrique.php` :

```php
<?php

declare(strict_types=1);

namespace App\Expense\Domain\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bareme_kilometrique')]
class BaremeKilometrique
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    private int $year;

    #[ORM\Column(type: Types::JSON)]
    private array $rates;

    public function __construct(int $year, array $rates)
    {
        $this->year = $year;
        $this->rates = $rates;
    }

    public function year(): int
    {
        return $this->year;
    }

    public function rates(): array
    {
        return $this->rates;
    }

    public function setRates(array $rates): void
    {
        $this->rates = $rates;
    }

    /** @return array{year: int, rates: array} */
    public function toArray(): array
    {
        return [
            'year' => $this->year,
            'rates' => $this->rates,
        ];
    }
}
```

- [ ] Créer `src/Expense/Domain/Repository/BaremeKilometriqueRepositoryInterface.php` :

```php
<?php

declare(strict_types=1);

namespace App\Expense\Domain\Repository;

use App\Expense\Domain\Entity\BaremeKilometrique;

interface BaremeKilometriqueRepositoryInterface
{
    public function findByYear(int $year): ?BaremeKilometrique;

    /** @return BaremeKilometrique[] */
    public function findAll(): array;

    public function save(BaremeKilometrique $bareme): void;
}
```

- [ ] Commit :

```bash
rtk git add src/Expense/Domain/Entity/BaremeKilometrique.php \
        src/Expense/Domain/Repository/BaremeKilometriqueRepositoryInterface.php
rtk git commit -m "feat(expense): add BaremeKilometrique entity and repository interface"
```

---

## Task 2 — Repository Doctrine

**Files:**
- Create: `src/Expense/Infrastructure/Persistence/DoctrineBaremeKilometriqueRepository.php`

- [ ] Créer `src/Expense/Infrastructure/Persistence/DoctrineBaremeKilometriqueRepository.php` :

```php
<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Persistence;

use App\Expense\Domain\Entity\BaremeKilometrique;
use App\Expense\Domain\Repository\BaremeKilometriqueRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineBaremeKilometriqueRepository implements BaremeKilometriqueRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findByYear(int $year): ?BaremeKilometrique
    {
        return $this->em->find(BaremeKilometrique::class, $year);
    }

    public function findAll(): array
    {
        return $this->em->getRepository(BaremeKilometrique::class)
            ->findBy([], ['year' => 'DESC']);
    }

    public function save(BaremeKilometrique $bareme): void
    {
        $this->em->persist($bareme);
        $this->em->flush();
    }
}
```

- [ ] Commit :

```bash
rtk git add src/Expense/Infrastructure/Persistence/DoctrineBaremeKilometriqueRepository.php
rtk git commit -m "feat(expense): add DoctrineBaremeKilometriqueRepository"
```

---

## Task 3 — Mettre à jour KilometricAllowanceCalculator + Tests

**Files:**
- Modify: `src/Expense/Domain/Service/KilometricAllowanceCalculator.php`
- Modify: `tests/Unit/Expense/Domain/KilometricAllowanceCalculatorTest.php`

**Contexte important :** `KilometricAllowanceCalculator` est actuellement instancié sans argument (`new KilometricAllowanceCalculator()`). En ajoutant un constructeur, Symfony l'auto-wire via le type-hint `BaremeKilometriqueRepositoryInterface` → `DoctrineBaremeKilometriqueRepository` (seule implémentation). Les tests Pest doivent passer un stub.

- [ ] Remplacer intégralement `src/Expense/Domain/Service/KilometricAllowanceCalculator.php` :

```php
<?php

declare(strict_types=1);

namespace App\Expense\Domain\Service;

use App\Expense\Domain\Repository\BaremeKilometriqueRepositoryInterface;

final class KilometricAllowanceCalculator
{
    public function __construct(
        private readonly BaremeKilometriqueRepositoryInterface $repository,
    ) {
    }

    private function resolveBareme(int $year): array
    {
        $entity = $this->repository->findByYear($year);
        if (null !== $entity) {
            return $entity->rates();
        }

        return BaremeKilometriqueProvider::forYear($year);
    }

    /**
     * @param array<array-key, array{distanceKm: float, vehiclePower: int|null, vehicleType?: string, isElectric?: bool, ...}> $trips
     */
    public function calculateAnnualDeduction(array $trips, int $year): float
    {
        $bareme = $this->resolveBareme($year);

        $buckets = [];
        foreach ($trips as $trip) {
            $type = $trip['vehicleType'] ?? 'car';
            $power = (int) ($trip['vehiclePower'] ?? 0);
            $electric = (bool) ($trip['isElectric'] ?? false);
            $key = $type.'|'.$power.'|'.($electric ? '1' : '0');
            if (!isset($buckets[$key])) {
                $buckets[$key] = ['type' => $type, 'power' => $power, 'electric' => $electric, 'km' => 0.0];
            }
            $buckets[$key]['km'] += (float) $trip['distanceKm'];
        }

        $total = 0.0;
        foreach ($buckets as $b) {
            $total += $this->calculateBucket($bareme, $b['type'], $b['power'], $b['km'], $b['electric']);
        }

        return round($total, 2);
    }

    /**
     * @param array{car: array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}>, motorcycle: array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}>, moped: array{rate1: float, rate2: float, fixed2: int, rate3: float}, electricMultiplier: float} $bareme
     */
    private function calculateBucket(array $bareme, string $type, int $power, float $km, bool $electric): float
    {
        $amount = match ($type) {
            'motorcycle' => $this->forMotorcycle($bareme['motorcycle'], $power, $km),
            'moped' => $this->applyTranches($bareme['moped'], $km, 3000, 6000),
            default => $this->forCar($bareme['car'], $power, $km),
        };

        return ($electric && 'car' === $type) ? $amount * $bareme['electricMultiplier'] : $amount;
    }

    /**
     * @param array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}> $carBareme
     */
    private function forCar(array $carBareme, int $power, float $km): float
    {
        $cv = min(max($power, 3), 7);

        return $this->applyTranches($carBareme[$cv], $km, 5000, 20000);
    }

    /**
     * @param array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}> $motoBareme
     */
    private function forMotorcycle(array $motoBareme, int $power, float $km): float
    {
        $group = match (true) {
            $power <= 2 => 1,
            $power <= 5 => 3,
            default => 6,
        };

        return $this->applyTranches($motoBareme[$group], $km, 3000, 6000);
    }

    /**
     * @param array{rate1: float, rate2: float, fixed2: int, rate3: float} $b
     */
    private function applyTranches(array $b, float $km, int $tranche1, int $tranche2): float
    {
        return match (true) {
            $km <= $tranche1 => $km * $b['rate1'],
            $km <= $tranche2 => ($km * $b['rate2']) + (float) $b['fixed2'],
            default => $km * $b['rate3'],
        };
    }

    public function calculateForPowerAndDistance(int $vehiclePower, float $totalKm, int $year = 2025): float
    {
        $bareme = $this->resolveBareme($year);

        return $this->forCar($bareme['car'], $vehiclePower, $totalKm);
    }
}
```

- [ ] Remplacer intégralement `tests/Unit/Expense/Domain/KilometricAllowanceCalculatorTest.php` :

```php
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
```

- [ ] Lancer les tests dans Docker : `make test c='tests/Unit/Expense/Domain/KilometricAllowanceCalculatorTest.php'`

Résultat attendu : tous les tests PASS

- [ ] Commit :

```bash
rtk git add src/Expense/Domain/Service/KilometricAllowanceCalculator.php \
        tests/Unit/Expense/Domain/KilometricAllowanceCalculatorTest.php
rtk git commit -m "feat(expense): make KilometricAllowanceCalculator injectable with DB fallback"
```

---

## Task 4 — Admin CQRS : Queries

**Files:**
- Create: `src/Admin/Application/Query/GetBaremeKilometrique/GetBaremeKilometriqueQuery.php`
- Create: `src/Admin/Application/Query/GetBaremeKilometrique/GetBaremeKilometriqueQueryHandler.php`
- Create: `src/Admin/Application/Query/ListBaremeKilometrique/ListBaremeKilometriqueQuery.php`
- Create: `src/Admin/Application/Query/ListBaremeKilometrique/ListBaremeKilometriqueQueryHandler.php`

- [ ] Créer `GetBaremeKilometriqueQuery.php` :

```php
<?php

declare(strict_types=1);

namespace App\Admin\Application\Query\GetBaremeKilometrique;

final readonly class GetBaremeKilometriqueQuery
{
    public function __construct(public readonly int $year)
    {
    }
}
```

- [ ] Créer `GetBaremeKilometriqueQueryHandler.php` :

```php
<?php

declare(strict_types=1);

namespace App\Admin\Application\Query\GetBaremeKilometrique;

use App\Expense\Domain\Repository\BaremeKilometriqueRepositoryInterface;
use App\SharedKernel\Application\Bus\QueryHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetBaremeKilometriqueQueryHandler implements QueryHandlerInterface
{
    public function __construct(private BaremeKilometriqueRepositoryInterface $repository)
    {
    }

    public function __invoke(GetBaremeKilometriqueQuery $query): ?array
    {
        return $this->repository->findByYear($query->year)?->toArray();
    }
}
```

- [ ] Créer `ListBaremeKilometriqueQuery.php` :

```php
<?php

declare(strict_types=1);

namespace App\Admin\Application\Query\ListBaremeKilometrique;

final readonly class ListBaremeKilometriqueQuery
{
}
```

- [ ] Créer `ListBaremeKilometriqueQueryHandler.php` :

```php
<?php

declare(strict_types=1);

namespace App\Admin\Application\Query\ListBaremeKilometrique;

use App\Expense\Domain\Entity\BaremeKilometrique;
use App\Expense\Domain\Repository\BaremeKilometriqueRepositoryInterface;
use App\SharedKernel\Application\Bus\QueryHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ListBaremeKilometriqueQueryHandler implements QueryHandlerInterface
{
    public function __construct(private BaremeKilometriqueRepositoryInterface $repository)
    {
    }

    /** @return list<array{year: int, rates: array}> */
    public function __invoke(ListBaremeKilometriqueQuery $query): array
    {
        return array_values(
            array_map(fn (BaremeKilometrique $b) => $b->toArray(), $this->repository->findAll())
        );
    }
}
```

- [ ] Commit :

```bash
rtk git add src/Admin/Application/Query/GetBaremeKilometrique/ \
        src/Admin/Application/Query/ListBaremeKilometrique/
rtk git commit -m "feat(admin): add GetBaremeKilometrique and ListBaremeKilometrique queries"
```

---

## Task 5 — Admin CQRS : Command

**Files:**
- Create: `src/Admin/Application/Command/UpsertBaremeKilometrique/UpsertBaremeKilometriqueCommand.php`
- Create: `src/Admin/Application/Command/UpsertBaremeKilometrique/UpsertBaremeKilometriqueCommandHandler.php`

- [ ] Créer `UpsertBaremeKilometriqueCommand.php` :

```php
<?php

declare(strict_types=1);

namespace App\Admin\Application\Command\UpsertBaremeKilometrique;

final readonly class UpsertBaremeKilometriqueCommand
{
    public function __construct(
        public readonly int $year,
        public readonly array $rates,
    ) {
    }
}
```

- [ ] Créer `UpsertBaremeKilometriqueCommandHandler.php` :

```php
<?php

declare(strict_types=1);

namespace App\Admin\Application\Command\UpsertBaremeKilometrique;

use App\Expense\Domain\Entity\BaremeKilometrique;
use App\Expense\Domain\Repository\BaremeKilometriqueRepositoryInterface;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpsertBaremeKilometriqueCommandHandler implements CommandHandlerInterface
{
    public function __construct(private BaremeKilometriqueRepositoryInterface $repository)
    {
    }

    public function __invoke(UpsertBaremeKilometriqueCommand $command): void
    {
        $bareme = $this->repository->findByYear($command->year)
            ?? new BaremeKilometrique($command->year, $command->rates);

        $bareme->setRates($command->rates);
        $this->repository->save($bareme);
    }
}
```

- [ ] Commit :

```bash
rtk git add src/Admin/Application/Command/UpsertBaremeKilometrique/
rtk git commit -m "feat(admin): add UpsertBaremeKilometrique command"
```

---

## Task 6 — Admin HTTP Controllers + Routes

**Files:**
- Create: `src/Admin/Infrastructure/Http/AdminBareme/GetController.php`
- Create: `src/Admin/Infrastructure/Http/AdminBareme/ListController.php`
- Create: `src/Admin/Infrastructure/Http/AdminBareme/UpsertController.php`
- Modify: `config/routes/api/admin.yaml`

- [ ] Créer `GetController.php` :

```php
<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminBareme;

use App\Admin\Application\Query\GetBaremeKilometrique\GetBaremeKilometriqueQuery;
use App\Admin\Infrastructure\Http\AbstractAdminController;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/bareme-kilometrique/{year}', name: 'get', requirements: ['year' => '\d{4}'], methods: [Request::METHOD_GET])]
class GetController extends AbstractAdminController
{
    public function __construct(private readonly QueryBusInterface $queryBus)
    {
    }

    public function __invoke(int $year): JsonResponse
    {
        $result = $this->queryBus->ask(new GetBaremeKilometriqueQuery($year));
        if (!$result) {
            return $this->json(['error' => 'No barème for this year'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($result);
    }
}
```

- [ ] Créer `ListController.php` :

```php
<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminBareme;

use App\Admin\Application\Query\ListBaremeKilometrique\ListBaremeKilometriqueQuery;
use App\Admin\Infrastructure\Http\AbstractAdminController;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/bareme-kilometrique', name: 'list', methods: [Request::METHOD_GET])]
class ListController extends AbstractAdminController
{
    public function __construct(private readonly QueryBusInterface $queryBus)
    {
    }

    public function __invoke(): JsonResponse
    {
        return $this->json($this->queryBus->ask(new ListBaremeKilometriqueQuery()));
    }
}
```

- [ ] Créer `UpsertController.php` :

```php
<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminBareme;

use App\Admin\Application\Command\UpsertBaremeKilometrique\UpsertBaremeKilometriqueCommand;
use App\Admin\Application\Query\GetBaremeKilometrique\GetBaremeKilometriqueQuery;
use App\Admin\Infrastructure\Http\AbstractAdminController;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/bareme-kilometrique/{year}', name: 'upsert', requirements: ['year' => '\d{4}'], methods: [Request::METHOD_PUT])]
class UpsertController extends AbstractAdminController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(int $year, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $rates = $data['rates'] ?? null;

        if (!$this->isValidRates($rates)) {
            return $this->json(['error' => 'Invalid rates structure'], Response::HTTP_BAD_REQUEST);
        }

        $this->commandBus->dispatch(new UpsertBaremeKilometriqueCommand(year: $year, rates: $rates));

        return $this->json($this->queryBus->ask(new GetBaremeKilometriqueQuery($year)));
    }

    private function isValidRates(mixed $rates): bool
    {
        if (!is_array($rates)) {
            return false;
        }

        foreach ([3, 4, 5, 6, 7] as $cv) {
            if (!isset($rates['car'][$cv]) || !$this->isValidTranche($rates['car'][$cv])) {
                return false;
            }
        }

        foreach ([1, 3, 6] as $group) {
            if (!isset($rates['motorcycle'][$group]) || !$this->isValidTranche($rates['motorcycle'][$group])) {
                return false;
            }
        }

        if (!isset($rates['moped']) || !$this->isValidTranche($rates['moped'])) {
            return false;
        }

        if (!isset($rates['electricMultiplier']) || !is_numeric($rates['electricMultiplier']) || $rates['electricMultiplier'] < 1) {
            return false;
        }

        return true;
    }

    private function isValidTranche(mixed $tranche): bool
    {
        return is_array($tranche)
            && isset($tranche['rate1'], $tranche['rate2'], $tranche['fixed2'], $tranche['rate3'])
            && is_numeric($tranche['rate1']) && $tranche['rate1'] > 0
            && is_numeric($tranche['rate2']) && $tranche['rate2'] > 0
            && is_numeric($tranche['fixed2']) && $tranche['fixed2'] > 0
            && is_numeric($tranche['rate3']) && $tranche['rate3'] > 0;
    }
}
```

- [ ] Ajouter à `config/routes/api/admin.yaml` (à la fin du fichier) :

```yaml

api_admin_bareme:
    resource: '../../../src/Admin/Infrastructure/Http/AdminBareme/'
    type: attribute
    prefix: /api/admin/
    trailing_slash_on_root: false
    name_prefix: adminBareme_
```

- [ ] Commit :

```bash
rtk git add src/Admin/Infrastructure/Http/AdminBareme/ config/routes/api/admin.yaml
rtk git commit -m "feat(admin): add bareme-kilometrique HTTP controllers and routes"
```

---

## Task 7 — Migration Doctrine

**Files:**
- Create: `migrations/Version20260506000001.php`

**Note :** Le type `JSON` Doctrine stocke un JSON valide en MySQL 8. `json_decode` avec les clés numériques `"3"`, `"4"` etc. les convertit automatiquement en int PHP → compatibilité totale avec `KilometricAllowanceCalculator`.

- [ ] Créer `migrations/Version20260506000001.php` :

```php
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260506000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create bareme_kilometrique table and seed 2023-2026 rates (arrêté du 27 mars 2023, reconduit)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE bareme_kilometrique (year INT NOT NULL, rates JSON NOT NULL, PRIMARY KEY(year)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');

        $rates = json_encode([
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
        ], JSON_THROW_ON_ERROR);

        foreach ([2023, 2024, 2025, 2026] as $year) {
            $this->connection->executeStatement(
                'INSERT INTO bareme_kilometrique (year, rates) VALUES (:year, :rates)',
                ['year' => $year, 'rates' => $rates]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE bareme_kilometrique');
    }
}
```

- [ ] Appliquer la migration : `make sf c='doctrine:migrations:migrate --no-interaction'`

Résultat attendu :
```
[OK] Successfully executed 1 migrations.
```

- [ ] Commit :

```bash
rtk git add migrations/Version20260506000001.php
rtk git commit -m "feat(migration): create bareme_kilometrique table and seed 2023-2026"
```

---

## Task 8 — Frontend : API Client

**Files:**
- Modify: `frontend/src/api/adminApi.ts`

- [ ] Ajouter les interfaces TypeScript après l'interface `FiscalConfig` dans `adminApi.ts` :

```typescript
export interface TrancheTaux {
  rate1: number
  rate2: number
  fixed2: number
  rate3: number
}

export interface BaremeRates {
  car: Record<number, TrancheTaux>
  motorcycle: Record<number, TrancheTaux>
  moped: TrancheTaux
  electricMultiplier: number
}

export interface BaremeKilometrique {
  year: number
  rates: BaremeRates
}
```

- [ ] Ajouter les méthodes dans l'objet `adminApi` (après `upsertFiscalConfig`) :

```typescript
  listBaremes: () =>
    http.get<BaremeKilometrique[]>('/admin/bareme-kilometrique').then(r => r.data),

  upsertBareme: (year: number, rates: BaremeRates) =>
    http.put<BaremeKilometrique>(`/admin/bareme-kilometrique/${year}`, { rates }).then(r => r.data),
```

- [ ] Commit :

```bash
rtk git add frontend/src/api/adminApi.ts
rtk git commit -m "feat(frontend): add barème kilométrique API client types and methods"
```

---

## Task 9 — Frontend : Vue View

**Files:**
- Create: `frontend/src/views/admin/AdminBaremeKilometriqueView.vue`

- [ ] Créer `frontend/src/views/admin/AdminBaremeKilometriqueView.vue` :

```vue
<template>
  <div class="p-6 max-w-5xl">
    <h2 class="text-xl font-bold text-gray-100 mb-1">Barèmes kilométriques</h2>
    <p class="text-sm text-gray-400 mb-6">Taux officiels par année fiscale — voitures, motos, cyclomoteurs.</p>

    <div v-if="loading" class="text-gray-400 text-sm">Chargement…</div>

    <div v-else class="space-y-3">
      <div
        v-for="row in rows" :key="row.year"
        class="bg-gray-800 border border-gray-700 rounded-xl overflow-hidden"
      >
        <!-- Header ligne -->
        <div class="px-5 py-4 flex items-center justify-between">
          <span class="text-white font-semibold text-lg">{{ row.year }}</span>
          <div class="flex items-center gap-3">
            <span v-if="row.savedAt" class="text-xs text-emerald-400">✓ Enregistré</span>
            <button
              @click="row.open = !row.open"
              class="px-4 py-1.5 text-sm font-medium bg-gray-700 text-gray-200 rounded-lg hover:bg-gray-600 transition-colors"
            >
              {{ row.open ? 'Fermer' : 'Modifier' }}
            </button>
          </div>
        </div>

        <!-- Éditeur dépliable -->
        <div v-if="row.open" class="border-t border-gray-700 px-5 py-5 space-y-6">

          <!-- Voitures -->
          <div>
            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">
              Voitures (thermiques, hybrides, hydrogène)
            </h3>
            <div class="grid grid-cols-[4rem_1fr_1fr_1fr_1fr] gap-2 text-xs text-gray-500 uppercase tracking-wider mb-2 px-1">
              <span>CV</span>
              <span>≤5 000 km (€/km)</span>
              <span>5–20k taux (€/km)</span>
              <span>5–20k forfait (€)</span>
              <span>&gt;20 000 km (€/km)</span>
            </div>
            <div
              v-for="cv in [3, 4, 5, 6, 7]" :key="cv"
              class="grid grid-cols-[4rem_1fr_1fr_1fr_1fr] gap-2 items-center mb-1.5"
            >
              <span class="text-gray-300 text-sm font-medium">{{ cv }} CV</span>
              <input v-model.number="row.draft.car[cv].rate1" type="number" step="0.001" :class="inputCls" />
              <input v-model.number="row.draft.car[cv].rate2" type="number" step="0.001" :class="inputCls" />
              <input v-model.number="row.draft.car[cv].fixed2" type="number" step="1" :class="inputCls" />
              <input v-model.number="row.draft.car[cv].rate3" type="number" step="0.001" :class="inputCls" />
            </div>
          </div>

          <!-- Motocyclettes -->
          <div>
            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Motocyclettes</h3>
            <div class="grid grid-cols-[6rem_1fr_1fr_1fr_1fr] gap-2 text-xs text-gray-500 uppercase tracking-wider mb-2 px-1">
              <span>Cylindrée</span>
              <span>≤3 000 km</span>
              <span>3–6k taux</span>
              <span>3–6k forfait</span>
              <span>&gt;6 000 km</span>
            </div>
            <div
              v-for="[key, label] in motoGroups" :key="key"
              class="grid grid-cols-[6rem_1fr_1fr_1fr_1fr] gap-2 items-center mb-1.5"
            >
              <span class="text-gray-300 text-sm">{{ label }}</span>
              <input v-model.number="row.draft.motorcycle[key].rate1" type="number" step="0.001" :class="inputCls" />
              <input v-model.number="row.draft.motorcycle[key].rate2" type="number" step="0.001" :class="inputCls" />
              <input v-model.number="row.draft.motorcycle[key].fixed2" type="number" step="1" :class="inputCls" />
              <input v-model.number="row.draft.motorcycle[key].rate3" type="number" step="0.001" :class="inputCls" />
            </div>
          </div>

          <!-- Cyclomoteurs -->
          <div>
            <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Cyclomoteurs</h3>
            <div class="grid grid-cols-[6rem_1fr_1fr_1fr_1fr] gap-2 text-xs text-gray-500 uppercase tracking-wider mb-2 px-1">
              <span></span>
              <span>≤3 000 km</span>
              <span>3–6k taux</span>
              <span>3–6k forfait</span>
              <span>&gt;6 000 km</span>
            </div>
            <div class="grid grid-cols-[6rem_1fr_1fr_1fr_1fr] gap-2 items-center">
              <span class="text-gray-300 text-sm">Cyclo</span>
              <input v-model.number="row.draft.moped.rate1" type="number" step="0.001" :class="inputCls" />
              <input v-model.number="row.draft.moped.rate2" type="number" step="0.001" :class="inputCls" />
              <input v-model.number="row.draft.moped.fixed2" type="number" step="1" :class="inputCls" />
              <input v-model.number="row.draft.moped.rate3" type="number" step="0.001" :class="inputCls" />
            </div>
          </div>

          <!-- Multiplicateur électrique -->
          <div class="flex items-center gap-4">
            <label class="text-sm text-gray-300 font-medium">Majoration véhicule électrique</label>
            <input
              v-model.number="row.draft.electricMultiplier"
              type="number" step="0.01" min="1"
              class="w-24 bg-gray-700 border border-gray-600 rounded-lg px-2 py-1.5 text-sm text-white focus:outline-none focus:border-indigo-500"
            />
            <span class="text-gray-500 text-xs">× (ex : 1.20 = +20 %)</span>
          </div>

          <!-- Bouton enregistrer -->
          <div class="flex justify-end pt-2 border-t border-gray-700">
            <button
              @click="save(row)"
              :disabled="row.saving"
              class="px-5 py-2 text-sm font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-500 transition-colors disabled:opacity-40"
            >
              {{ row.saving ? 'Enregistrement…' : 'Enregistrer' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { adminApi, type BaremeKilometrique, type BaremeRates } from '@/api/adminApi'

const motoGroups: [number, string][] = [[1, '1-2 CV'], [3, '3-5 CV'], [6, '+5 CV']]

const inputCls = 'w-full bg-gray-700 border border-gray-600 rounded-lg px-2 py-1.5 text-sm text-white focus:outline-none focus:border-indigo-500'

interface Row {
  year: number
  draft: BaremeRates
  open: boolean
  saving: boolean
  savedAt: boolean
}

function cloneRates(rates: BaremeRates): BaremeRates {
  return JSON.parse(JSON.stringify(rates))
}

const loading = ref(true)
const rows = ref<Row[]>([])

async function load() {
  const baremes = await adminApi.listBaremes()
  rows.value = baremes.map((b: BaremeKilometrique) => ({
    year: b.year,
    draft: cloneRates(b.rates),
    open: false,
    saving: false,
    savedAt: false,
  }))
}

async function save(row: Row) {
  row.saving = true
  try {
    await adminApi.upsertBareme(row.year, row.draft)
    row.savedAt = true
    setTimeout(() => { row.savedAt = false }, 2000)
  } finally {
    row.saving = false
  }
}

onMounted(async () => {
  try {
    await load()
  } finally {
    loading.value = false
  }
})
</script>
```

- [ ] Commit :

```bash
rtk git add frontend/src/views/admin/AdminBaremeKilometriqueView.vue
rtk git commit -m "feat(frontend): add AdminBaremeKilometriqueView with inline editor"
```

---

## Task 10 — Router + Sidebar

**Files:**
- Modify: `frontend/src/router/index.ts`
- Modify: `frontend/src/App.vue`

- [ ] Dans `frontend/src/router/index.ts`, ajouter après la route `/admin/fiscal-config` :

```typescript
    { path: '/admin/bareme-kilometrique', component: () => import('@/views/admin/AdminBaremeKilometriqueView.vue'), meta: { admin: true } },
```

- [ ] Dans `frontend/src/App.vue`, ajouter dans le tableau `adminNav` (après l'entrée `fiscal-config`) :

```typescript
  { to: '/admin/bareme-kilometrique', label: 'Barèmes km', icon: '🚗' },
```

- [ ] Lancer le typecheck : `cd frontend && npm run typecheck`

Résultat attendu : aucune erreur TypeScript

- [ ] Commit :

```bash
rtk git add frontend/src/router/index.ts frontend/src/App.vue
rtk git commit -m "feat(frontend): wire barème kilométrique route and admin sidebar link"
```