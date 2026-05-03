<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminUser;

use App\Admin\Infrastructure\Http\AbstractAdminController;
use App\Auth\Domain\Entity\User;
use App\Auth\Domain\Repository\UserRepositoryInterface;
use App\Person\Domain\Repository\PersonRepositoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/users', name: 'list', methods: [Request::METHOD_GET])]
class ListController extends AbstractAdminController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly PersonRepositoryInterface $personRepository,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $search = $request->query->get('search', '');
        $status = $request->query->get('status', '');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;

        $users = $search
            ? $this->userRepository->findByEmailLike($search)
            : $this->userRepository->findAll();

        if ('' !== $status) {
            $users = array_values(array_filter($users, fn (User $u) => $u->subscriptionStatus() === $status));
        }

        $total = count($users);
        $offset = ($page - 1) * $limit;
        $paged = array_slice($users, $offset, $limit);

        $items = array_map(function (User $u) {
            $persons = $this->personRepository->findAllByUserId($u->id()->value());

            return array_merge($u->toArray(), ['personCount' => count($persons)]);
        }, $paged);

        return $this->json([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => (int) ceil($total / $limit),
        ]);
    }
}
