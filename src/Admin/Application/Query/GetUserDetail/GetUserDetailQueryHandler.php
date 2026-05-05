<?php

declare(strict_types=1);

namespace App\Admin\Application\Query\GetUserDetail;

use App\Auth\Domain\Repository\UserRepositoryInterface;
use App\Auth\Domain\ValueObject\UserId;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Person\Domain\Repository\PersonRepositoryInterface;
use App\SharedKernel\Application\Bus\QueryHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetUserDetailQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PersonRepositoryInterface $personRepository,
        private ExpenseRepositoryInterface $expenseRepository,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function __invoke(GetUserDetailQuery $query): ?array
    {
        $user = $this->userRepository->findById(UserId::fromString($query->userId));
        if (!$user) {
            return null;
        }

        $persons = $this->personRepository->findAllByUserId($query->userId);
        $personsData = array_map(function ($person) {
            return array_merge($person->toArray(), [
                'expenseCount' => $this->expenseRepository->countByPersonId($person->id()->value()),
            ]);
        }, $persons);

        return array_merge($user->toArray(), ['persons' => $personsData]);
    }
}
