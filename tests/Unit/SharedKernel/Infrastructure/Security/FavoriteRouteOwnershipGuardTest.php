<?php

declare(strict_types=1);

use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Person\Domain\Entity\FavoriteRoute;
use App\Person\Domain\Entity\Person;
use App\Person\Domain\Repository\FavoriteRouteRepositoryInterface;
use App\Person\Domain\Repository\PersonRepositoryInterface;
use App\Person\Domain\ValueObject\FavoriteRouteId;
use App\Person\Domain\ValueObject\PersonId;
use App\SharedKernel\Infrastructure\Security\OwnershipGuard;

it('refuses a favorite route when the URL person differs from the route owner', function () {
    $personId = '00000000-0000-4000-8000-000000000105';
    $routeId = '00000000-0000-4000-8000-000000000205';
    $route = new FavoriteRoute(FavoriteRouteId::fromString($routeId), $personId, 'Maison', 'Paris', 'Lyon', 'car', 5, false, false);
    $persons = new class($personId) implements PersonRepositoryInterface {
        public function __construct(private string $id) {}
        public function save(Person $person): void {}
        public function delete(Person $person): void {}
        public function findById(PersonId $id): ?Person { return new Person($id, 'user-1', 'Alice', 'Martin'); }
        public function findAllByUserId(string $userId): array { return []; }
    };
    $routes = new class($route) implements FavoriteRouteRepositoryInterface {
        public function __construct(private FavoriteRoute $route) {}
        public function save(FavoriteRoute $route): void {}
        public function delete(FavoriteRoute $route): void {}
        public function findById(FavoriteRouteId $id): ?FavoriteRoute { return $this->route; }
        public function findByPersonId(string $personId): array { return []; }
    };
    $expenses = new class implements ExpenseRepositoryInterface {
        public function save(\App\Expense\Domain\Entity\Expense $expense): void {}
        public function delete(\App\Expense\Domain\Entity\Expense $expense): void {}
        public function findById(\App\Expense\Domain\ValueObject\ExpenseId $id): ?\App\Expense\Domain\Entity\Expense { return null; }
        public function findByPersonAndPeriod(string $personId, DateTimeImmutable $from, DateTimeImmutable $to): array { return []; }
        public function findByPersonAndYear(string $personId, int $year): array { return []; }
        public function findByPeriod(DateTimeImmutable $from, DateTimeImmutable $to): array { return []; }
        public function countByPersonId(string $personId): int { return 0; }
    };
    $guard = new OwnershipGuard($persons, $expenses, $routes);

    expect(fn () => $guard->assertFavoriteRouteBelongsToUser($routeId, '00000000-0000-4000-8000-000000000999', 'user-1'))
        ->toThrow(\App\SharedKernel\Domain\Exception\NotFoundException::class);
});
