<?php

declare(strict_types=1);

namespace App\Person\Infrastructure\Http\Person;

use App\Auth\Domain\Entity\User;
use App\Person\Application\Command\DeletePerson\DeletePersonCommand;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use App\SharedKernel\Infrastructure\Security\OwnershipGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/{id}', name: 'delete', requirements: ['id' => Requirement::UUID_V4], methods: ['DELETE'])]
class DeleteController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly OwnershipGuard $ownershipGuard,
    ) {
    }

    public function __invoke(string $id): JsonResponse
    {
        $this->ownershipGuard->assertPersonBelongsToUser($id, $this->currentUserId());

        $this->commandBus->dispatch(new DeletePersonCommand($id));

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function currentUserId(): string
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user->id()->value();
    }
}
