<?php

declare(strict_types=1);

namespace App\Admin\Application\Query\ExportUsersCsv;

use App\Auth\Domain\Repository\UserRepositoryInterface;
use App\Person\Domain\Repository\PersonRepositoryInterface;
use App\SharedKernel\Application\Bus\QueryHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class ExportUsersCsvQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PersonRepositoryInterface $personRepository,
    ) {
    }

    /** @return list<array{id: string, email: string, status: string, createdAt: string, personCount: int}> */
    public function __invoke(ExportUsersCsvQuery $query): array
    {
        return array_map(function ($user) {
            $persons = $this->personRepository->findAllByUserId($user->id()->value());

            return [
                'id' => $user->id()->value(),
                'email' => $user->email(),
                'status' => $user->subscriptionStatus() ?? '',
                'createdAt' => $user->createdAt()->format('Y-m-d H:i:s'),
                'personCount' => count($persons),
            ];
        }, $this->userRepository->findAll());
    }
}
