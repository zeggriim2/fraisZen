<?php

declare(strict_types=1);

namespace App\Person\Infrastructure\Http;

use App\Auth\Domain\Entity\User;
use App\Person\Application\Command\CreateFavoriteRoute\CreateFavoriteRouteCommand;
use App\Person\Application\Command\DeleteFavoriteRoute\DeleteFavoriteRouteCommand;
use App\Person\Application\Command\UpdateFavoriteRoute\UpdateFavoriteRouteCommand;
use App\Person\Application\Query\GetFavoriteRoutesByPerson\GetFavoriteRoutesByPersonQuery;
use App\Person\Application\Query\GetPersonById\GetPersonByIdQuery;
use App\Person\Domain\Exception\FavoriteRouteNotFoundException;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/persons/{personId}/favorite-routes', name: 'favoriteRoute_', requirements: ['personId' => Requirement::UUID_V4])]
final class FavoriteRouteController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    #[Route('', name: 'list', methods: [Request::METHOD_GET])]
    public function list(string $personId): JsonResponse
    {
        if (!$this->personBelongsToUser($personId)) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->queryBus->ask(new GetFavoriteRoutesByPersonQuery($personId)));
    }

    #[Route('', name: 'create', methods: [Request::METHOD_POST])]
    public function create(string $personId, Request $request): JsonResponse
    {
        if (!$this->personBelongsToUser($personId)) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }
        $data = json_decode($request->getContent(), true) ?? [];
        $id = $this->commandBus->dispatch(new CreateFavoriteRouteCommand(
            personId: $personId,
            name: $data['name'] ?? '',
            departure: $data['departure'] ?? '',
            arrival: $data['arrival'] ?? '',
            vehicleType: $data['vehicleType'] ?? 'car',
            vehiclePower: isset($data['vehiclePower']) ? (int) $data['vehiclePower'] : null,
            isElectric: (bool) ($data['isElectric'] ?? false),
            roundTrip: (bool) ($data['roundTrip'] ?? false),
        ));

        $routes = $this->queryBus->ask(new GetFavoriteRoutesByPersonQuery($personId));
        $created = array_values(array_filter($routes, fn ($r) => $r['id'] === $id))[0] ?? [];

        return $this->json($created, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => Requirement::UUID_V4], methods: [Request::METHOD_PUT])]
    public function update(string $personId, string $id, Request $request): JsonResponse
    {
        if (!$this->personBelongsToUser($personId)) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }
        $data = json_decode($request->getContent(), true) ?? [];
        try {
            $this->commandBus->dispatch(new UpdateFavoriteRouteCommand(
                id: $id,
                name: $data['name'] ?? '',
                departure: $data['departure'] ?? '',
                arrival: $data['arrival'] ?? '',
                vehicleType: $data['vehicleType'] ?? 'car',
                vehiclePower: isset($data['vehiclePower']) ? (int) $data['vehiclePower'] : null,
                isElectric: (bool) ($data['isElectric'] ?? false),
                roundTrip: (bool) ($data['roundTrip'] ?? false),
            ));
            $routes = $this->queryBus->ask(new GetFavoriteRoutesByPersonQuery($personId));
            $updated = array_values(array_filter($routes, fn ($r) => $r['id'] === $id))[0] ?? [];

            return $this->json($updated);
        } catch (HandlerFailedException $e) {
            if ($e->getPrevious() instanceof FavoriteRouteNotFoundException) {
                return $this->json(['error' => $e->getPrevious()->getMessage()], Response::HTTP_NOT_FOUND);
            }
            throw $e;
        }
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => Requirement::UUID_V4], methods: ['DELETE'])]
    public function delete(string $personId, string $id): JsonResponse
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
