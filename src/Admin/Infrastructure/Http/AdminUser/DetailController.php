<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminUser;

use App\Admin\Infrastructure\Http\AbstractAdminController;
use App\Auth\Domain\Repository\UserRepositoryInterface;
use App\Auth\Domain\ValueObject\UserId;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Person\Domain\Repository\PersonRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/users/{id}', name: 'detail', requirements: ['id' => Requirement::UUID_V4], methods: [Request::METHOD_GET])]
class DetailController extends AbstractAdminController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PersonRepositoryInterface $personRepository,
        private readonly ExpenseRepositoryInterface $expenseRepository,
    ) {
    }

    public function __invoke(string $id): JsonResponse
    {
        $user = $this->userRepository->findById(UserId::fromString($id));
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $persons = $this->personRepository->findAllByUserId($id);

        $personsData = array_map(function ($person) {
            return array_merge($person->toArray(), [
                'expenseCount' => $this->expenseRepository->countByPersonId($person->id()->value()),
            ]);
        }, $persons);

        return $this->json(array_merge($user->toArray(), [
            'persons' => $personsData,
        ]));
    }
}
