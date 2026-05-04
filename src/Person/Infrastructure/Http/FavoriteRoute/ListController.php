<?php

declare(strict_types=1);

namespace App\Person\Infrastructure\Http\FavoriteRoute;

use App\Auth\Domain\Entity\User;
use App\Person\Application\Query\GetFavoriteRoutesByPerson\GetFavoriteRoutesByPersonQuery;
use App\Person\Application\Query\GetPersonById\GetPersonByIdQuery;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('', name: 'list', methods: [Request::METHOD_GET])]
class ListController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(string $personId): JsonResponse
    {
        if (!$this->personBelongsToUser($personId)) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->queryBus->ask(new GetFavoriteRoutesByPersonQuery($personId)));
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
