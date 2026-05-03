<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminUser;

use App\Admin\Infrastructure\Http\AbstractAdminController;
use App\Auth\Domain\Repository\UserRepositoryInterface;
use App\Auth\Domain\ValueObject\UserId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/users/{id}/subscription', name: 'updateSubscription', requirements: ['id' => Requirement::UUID_V4], methods: [Request::METHOD_PATCH])]
class UpdateSubscriptionController extends AbstractAdminController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function __invoke(string $id, Request $request): JsonResponse
    {
        $user = $this->userRepository->findById(UserId::fromString($id));
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $status = $data['status'] ?? '';

        if (!in_array($status, ['active', 'canceled', 'inactive'], true)) {
            return $this->json(['error' => 'Invalid status'], Response::HTTP_BAD_REQUEST);
        }

        $user->setSubscriptionStatus($status);
        $this->userRepository->save($user);

        return $this->json(['success' => true]);
    }
}
