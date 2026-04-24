<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Http;

use App\Auth\Application\Command\RegisterUser\RegisterUserCommand;
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
    public function __construct(private readonly CommandBusInterface $commandBus) {}

    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(): never
    {
        // Intercepted by Symfony Security (json_login firewall) — never reached.
        throw new \LogicException('Should not be reached.');
    }

    #[Route('/register', methods: ['POST'])]
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

    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var \App\Auth\Domain\Entity\User $user */
        $user = $this->getUser();

        return $this->json($user->toArray());
    }
}
