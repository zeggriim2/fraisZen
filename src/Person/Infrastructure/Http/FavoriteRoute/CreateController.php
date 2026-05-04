<?php

declare(strict_types=1);

namespace App\Person\Infrastructure\Http\FavoriteRoute;

use App\Auth\Domain\Entity\User;
use App\Person\Application\Command\CreateFavoriteRoute\CreateFavoriteRouteCommand;
use App\Person\Application\Query\GetFavoriteRoutesByPerson\GetFavoriteRoutesByPersonQuery;
use App\Person\Application\Query\GetPersonById\GetPersonByIdQuery;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('', name: 'create', methods: [Request::METHOD_POST])]
class CreateController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(string $personId, Request $request): JsonResponse
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
