<?php

declare(strict_types=1);

namespace App\Person\Infrastructure\Http\Person;

use App\Auth\Domain\Entity\User;
use App\Person\Application\Query\GetPersonById\GetPersonByIdQuery;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use App\SharedKernel\Infrastructure\Security\OwnershipGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/{id}', name: 'show', requirements: ['id' => Requirement::UUID_V4], methods: [Request::METHOD_GET])]
class ShowController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly OwnershipGuard $ownershipGuard,
    ) {
    }

    public function __invoke(string $id): JsonResponse
    {
        $this->ownershipGuard->assertPersonBelongsToUser($id, $this->currentUserId());

        return $this->json($this->queryBus->ask(new GetPersonByIdQuery($id)));
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
