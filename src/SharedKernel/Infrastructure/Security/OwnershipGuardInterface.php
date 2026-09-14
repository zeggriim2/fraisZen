<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Security;

use App\Expense\Domain\Entity\Expense;

interface OwnershipGuardInterface
{
    public function assertPersonBelongsToUser(string $personId, string $userId): void;

    public function assertExpenseBelongsToUser(string $expenseId, string $userId): Expense;

    public function assertFavoriteRouteBelongsToUser(string $routeId, string $personId, string $userId): void;
}
