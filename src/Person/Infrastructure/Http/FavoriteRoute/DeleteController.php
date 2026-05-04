<?php

declare(strict_types=1);

namespace App\Person\Infrastructure\Http\FavoriteRoute;

use App\Auth\Domain\Entity\User;
use App\Person\Application\Command\DeleteFavoriteRoute\DeleteFavoriteRouteCommand;
use App\Person\Application\Query\GetPersonById\GetPersonByIdQuery;
use App\Person\Domain\Exception\FavoriteRouteNotFoundException;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/{id}', name: 'delete', requirements: ['id' => Requirement::UUID_V4], methods: ['DELETE'])]
class DeleteController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(string $personId, string $id): JsonResponse
    {
        if (!$this->personBelongsToUser($personId)) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }
        try {
            $this->commandBus->dispatch(new DeleteFavoriteRouteCommand($id));

            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (HandlerFailedException $e) {
            if ($e->getPrevious() instanceof FavoriteRouteNotFoundException) {
                return $this->json(['error' => $e->getPrevious()->getMessage()], Response::HTTP_NOT_FOUND);
            }
            throw $e;
        }
    }

    private function personBelongsToUser(string $personId): bool
    {
        try {
            $person = $this->queryBus->ask(new GetPersonByIdQuery($personId));
        } catch (\Throwable) {
            return false;
        }

        /** @var User $user */
        $user = $this->getUser();

        return ($person['userId'] ?? null) === $user->id()->value();
    }
}
