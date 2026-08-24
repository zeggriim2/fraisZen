<?php

declare(strict_types=1);

use App\Expense\Domain\Entity\Expense;
use App\Expense\Domain\Entity\TollExpense;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\Person\Domain\Entity\Person;
use App\Person\Domain\Repository\PersonRepositoryInterface;
use App\Person\Domain\ValueObject\PersonId;
use App\SharedKernel\Infrastructure\Security\OwnershipGuard;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

function makePersonForGuard(string $id, string $userId): Person
{
    return new Person(PersonId::fromString($id), $userId, 'Alice', 'Martin');
}

function makeTollExpenseForGuard(string $id, string $personId): TollExpense
{
    return new TollExpense(ExpenseId::fromString($id), $personId, new DateTimeImmutable('2025-01-10'), null, 12.50, 'Paris', 'Lyon');
}

function makePersonRepositoryForGuard(array $personsById): PersonRepositoryInterface
{
    return new class($personsById) implements PersonRepositoryInterface {
        /** @param array<string, Person> $personsById */
        public function __construct(private array $personsById) {}

        public function save(Person $person): void {}

        public function delete(Person $person): void {}

        public function findById(PersonId $id): ?Person
        {
            return $this->personsById[$id->value()] ?? null;
        }

        public function findAllByUserId(string $userId): array
        {
            return array_values(array_filter(
                $this->personsById,
                static fn (Person $person): bool => $person->userId() === $userId,
            ));
        }
    };
}

function makeExpenseRepositoryForGuard(array $expensesById): ExpenseRepositoryInterface
{
    return new class($expensesById) implements ExpenseRepositoryInterface {
        /** @param array<string, Expense> $expensesById */
        public function __construct(private array $expensesById) {}

        public function save(Expense $expense): void {}

        public function delete(Expense $expense): void {}

        public function findById(ExpenseId $id): ?Expense
        {
            return $this->expensesById[$id->value()] ?? null;
        }

        public function findByPersonAndPeriod(string $personId, DateTimeImmutable $from, DateTimeImmutable $to): array { return []; }

        public function findByPersonAndYear(string $personId, int $year): array { return []; }

        public function findByPeriod(DateTimeImmutable $from, DateTimeImmutable $to): array { return []; }

        public function countByPersonId(string $personId): int { return 0; }
    };
}

it('autorise l’accès à une personne appartenant à l’utilisateur connecté', function () {
    $personId = '00000000-0000-4000-8000-000000000101';
    $guard = new OwnershipGuard(
        makePersonRepositoryForGuard([$personId => makePersonForGuard($personId, 'user-1')]),
        makeExpenseRepositoryForGuard([]),
    );

    $guard->assertPersonBelongsToUser($personId, 'user-1');

    expect(true)->toBeTrue();
});

it('bloque l’accès à une personne appartenant à un autre utilisateur', function () {
    $personId = '00000000-0000-4000-8000-000000000102';
    $guard = new OwnershipGuard(
        makePersonRepositoryForGuard([$personId => makePersonForGuard($personId, 'owner-user')]),
        makeExpenseRepositoryForGuard([]),
    );

    expect(fn () => $guard->assertPersonBelongsToUser($personId, 'other-user'))
        ->toThrow(AccessDeniedException::class);
});

it('autorise l’accès à une dépense quand sa personne appartient à l’utilisateur connecté', function () {
    $personId = '00000000-0000-4000-8000-000000000103';
    $expenseId = '00000000-0000-4000-8000-000000000203';
    $expense = makeTollExpenseForGuard($expenseId, $personId);
    $guard = new OwnershipGuard(
        makePersonRepositoryForGuard([$personId => makePersonForGuard($personId, 'user-1')]),
        makeExpenseRepositoryForGuard([$expenseId => $expense]),
    );

    $result = $guard->assertExpenseBelongsToUser($expenseId, 'user-1');

    expect($result)->toBe($expense);
});

it('bloque l’accès à une dépense rattachée à une personne d’un autre utilisateur', function () {
    $personId = '00000000-0000-4000-8000-000000000104';
    $expenseId = '00000000-0000-4000-8000-000000000204';
    $guard = new OwnershipGuard(
        makePersonRepositoryForGuard([$personId => makePersonForGuard($personId, 'owner-user')]),
        makeExpenseRepositoryForGuard([$expenseId => makeTollExpenseForGuard($expenseId, $personId)]),
    );

    expect(fn () => $guard->assertExpenseBelongsToUser($expenseId, 'other-user'))
        ->toThrow(AccessDeniedException::class);
});
