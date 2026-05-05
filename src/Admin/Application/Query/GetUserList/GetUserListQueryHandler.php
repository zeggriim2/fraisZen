<?php

declare(strict_types=1);

namespace App\Admin\Application\Query\GetUserList;

use App\Auth\Domain\Entity\User;
use App\Auth\Domain\Repository\UserRepositoryInterface;
use App\Person\Domain\Repository\PersonRepositoryInterface;
use App\SharedKernel\Application\Bus\QueryHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetUserListQueryHandler implements QueryHandlerInterface
{
    private const PAGE_SIZE = 20;

    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PersonRepositoryInterface $personRepository,
    ) {
    }

    /** @return array{items: list<array<string, mixed>>, total: int, page: int, pages: int} */
    public function __invoke(GetUserListQuery $query): array
    {
        $users = $query->search
            ? $this->userRepository->findByEmailLike($query->search)
            : $this->userRepository->findAll();

        if ('' !== $query->status) {
            $users = array_values(array_filter($users, fn (User $u) => $u->subscriptionStatus() === $query->status));
        }

        $total = count($users);
        $paged = array_slice($users, ($query->page - 1) * self::PAGE_SIZE, self::PAGE_SIZE);

        $items = array_values(array_map(function (User $u) {
            $persons = $this->personRepository->findAllByUserId($u->id()->value());

            return array_merge($u->toArray(), ['personCount' => count($persons)]);
        }, $paged));

        return [
            'items' => $items,
            'total' => $total,
            'page' => $query->page,
            'pages' => (int) ceil($total / self::PAGE_SIZE),
        ];
    }
}
