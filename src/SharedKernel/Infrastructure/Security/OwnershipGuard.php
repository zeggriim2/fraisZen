<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Security;

use App\Expense\Domain\Entity\Expense;
use App\Expense\Domain\Exception\ExpenseNotFoundException;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\Person\Domain\Exception\PersonNotFoundException;
use App\Person\Domain\Repository\PersonRepositoryInterface;
use App\Person\Domain\ValueObject\PersonId;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class OwnershipGuard
{
    public function __construct(
        private PersonRepositoryInterface $personRepository,
        private ExpenseRepositoryInterface $expenseRepository,
    ) {
    }

    public function assertPersonBelongsToUser(string $personId, string $userId): void
    {
        $person = $this->personRepository->findById(PersonId::fromString($personId))
            ?? throw PersonNotFoundException::withId($personId);

        if ($person->userId() !== $userId) {
            throw new AccessDeniedException('Cette ressource ne vous appartient pas.');
        }
    }

    public function assertExpenseBelongsToUser(string $expenseId, string $userId): Expense
    {
        $expense = $this->expenseRepository->findById(ExpenseId::fromString($expenseId))
            ?? throw ExpenseNotFoundException::withId($expenseId);

        $this->assertPersonBelongsToUser($expense->personId(), $userId);

        return $expense;
    }
}
