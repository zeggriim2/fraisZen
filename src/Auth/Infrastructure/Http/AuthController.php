<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Http;

use App\Auth\Application\Command\DeleteUser\DeleteUserCommand;
use App\Auth\Application\Command\RegisterUser\RegisterUserCommand;
use App\Auth\Application\Command\UpdatePassword\UpdatePasswordCommand;
use App\Auth\Application\Command\UpdateProfile\UpdateProfileCommand;
use App\Auth\Domain\Entity\User;
use App\Auth\Domain\Exception\UserAlreadyExistsException;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
final class AuthController extends AbstractController
{
    public function __construct(private readonly CommandBusInterface $commandBus)
    {
    }

    #[Route('/login', name: 'api_login', methods: [Request::METHOD_POST])]
    public function login(): never
    {
        // Intercepted by Symfony Security (json_login firewall) — never reached.
        throw new \LogicException('Should not be reached.');
    }

    #[Route('/register', methods: [Request::METHOD_POST])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $id = $this->commandBus->dispatch(new RegisterUserCommand(
                email: $data['email'] ?? '',
                plainPassword: $data['password'] ?? '',
            ));
        } catch (HandlerFailedException $e) {
            if ($e->getPrevious() instanceof UserAlreadyExistsException) {
                return $this->json(['error' => $e->getPrevious()->getMessage()], Response::HTTP_CONFLICT);
            }
            throw $e;
        }

        return $this->json([
            'id' => $id,
            'email' => $data['email'],
            'roles' => ['ROLE_USER'],
            'subscriptionStatus' => null,
            'createdAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], Response::HTTP_CREATED);
    }

    #[Route('/me', methods: [Request::METHOD_GET])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json($user->toArray());
    }

    #[Route('/me', methods: [Request::METHOD_PATCH])]
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $this->commandBus->dispatch(new UpdateProfileCommand(
                userId: $user->id()->value(),
                email: $data['email'] ?? $user->email(),
                defaultYear: isset($data['defaultYear']) ? (int) $data['defaultYear'] : null,
                defaultFiscalPower: isset($data['defaultFiscalPower']) ? (int) $data['defaultFiscalPower'] : null,
            ));
        } catch (HandlerFailedException $e) {
            if ($e->getPrevious() instanceof UserAlreadyExistsException) {
                return $this->json(['error' => $e->getPrevious()->getMessage()], Response::HTTP_CONFLICT);
            }
            throw $e;
        }

        return $this->json(['success' => true]);
    }

    #[Route('/me/password', methods: [Request::METHOD_PATCH])]
    public function updatePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        try {
            $this->commandBus->dispatch(new UpdatePasswordCommand(
                userId: $user->id()->value(),
                currentPassword: $data['currentPassword'] ?? '',
                newPassword: $data['newPassword'] ?? '',
            ));
        } catch (HandlerFailedException $e) {
            if ($e->getPrevious() instanceof \DomainException) {
                return $this->json(['error' => $e->getPrevious()->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            throw $e;
        }

        return $this->json(['success' => true]);
    }

    #[Route('/me', methods: [Request::METHOD_DELETE])]
    public function deleteAccount(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->commandBus->dispatch(new DeleteUserCommand($user->id()->value()));

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
