<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Security;

use App\Expense\Domain\Entity\Expense;
use App\Expense\Domain\Exception\ExpenseNotFoundException;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\Person\Domain\Exception\FavoriteRouteNotFoundException;
use App\Person\Domain\Exception\PersonNotFoundException;
use App\Person\Domain\Repository\FavoriteRouteRepositoryInterface;
use App\Person\Domain\Repository\PersonRepositoryInterface;
use App\Person\Domain\ValueObject\FavoriteRouteId;
use App\Person\Domain\ValueObject\PersonId;

final readonly class OwnershipGuard
{
    public function __construct(
        private PersonRepositoryInterface $personRepository,
        private ExpenseRepositoryInterface $expenseRepository,
        private FavoriteRouteRepositoryInterface $favoriteRouteRepository,
    ) {
    }

    public function assertPersonBelongsToUser(string $personId, string $userId): void
    {
        $person = $this->personRepository->findById(PersonId::fromString($personId))
            ?? throw PersonNotFoundException::withId($personId);

        if ($person->userId() !== $userId) {
            throw PersonNotFoundException::withId($personId);
        }
    }

    public function assertExpenseBelongsToUser(string $expenseId, string $userId): Expense
    {
        $expense = $this->expenseRepository->findById(ExpenseId::fromString($expenseId))
            ?? throw ExpenseNotFoundException::withId($expenseId);

        $this->assertPersonBelongsToUser($expense->personId(), $userId);

        return $expense;
    }

    public function assertFavoriteRouteBelongsToUser(string $routeId, string $personId, string $userId): void
    {
        $route = $this->favoriteRouteRepository->findById(FavoriteRouteId::fromString($routeId))
            ?? throw FavoriteRouteNotFoundException::withId($routeId);

        if ($route->personId() !== $personId) {
            throw FavoriteRouteNotFoundException::withId($routeId);
        }

        $this->assertPersonBelongsToUser($personId, $userId);
    }
}
