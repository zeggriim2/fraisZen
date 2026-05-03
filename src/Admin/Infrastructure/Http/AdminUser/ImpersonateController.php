<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminUser;

use App\Admin\Infrastructure\Http\AbstractAdminController;
use App\Auth\Domain\Repository\UserRepositoryInterface;
use App\Auth\Domain\ValueObject\UserId;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/users/{id}/impersonate', name: 'impersonate', requirements: ['id' => Requirement::UUID_V4], methods: [Request::METHOD_POST])]
class ImpersonateController extends AbstractAdminController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly JWTEncoderInterface $jwtEncoder,
    ) {
    }

    public function __invoke(string $id): JsonResponse
    {
        $user = $this->userRepository->findById(UserId::fromString($id));
        if (!$user) {
            return $this->json(['error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        $token = $this->jwtEncoder->encode([
            'username' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        ]);

        return $this->json(['token' => $token]);
    }
}
