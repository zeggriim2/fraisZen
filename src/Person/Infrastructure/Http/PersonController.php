<?php

declare(strict_types=1);

namespace App\Person\Infrastructure\Http;

use App\Person\Application\Command\CreatePerson\CreatePersonCommand;
use App\Person\Application\Command\DeletePerson\DeletePersonCommand;
use App\Person\Application\Command\UpdatePerson\UpdatePersonCommand;
use App\Person\Application\Query\GetAllPersons\GetAllPersonsQuery;
use App\Person\Application\Query\GetPersonById\GetPersonByIdQuery;
use App\Person\Domain\Exception\PersonNotFoundException;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/persons')]
final class PersonController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return $this->json($this->queryBus->ask(new GetAllPersonsQuery()));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        try {
            return $this->json($this->queryBus->ask(new GetPersonByIdQuery($id)));
        } catch (HandlerFailedException $e) {
            if ($e->getPrevious() instanceof PersonNotFoundException) {
                return $this->json(['error' => $e->getPrevious()->getMessage()], Response::HTTP_NOT_FOUND);
            }
            throw $e;
        }
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $this->commandBus->dispatch(new CreatePersonCommand(
            firstName: $data['firstName'] ?? '',
            lastName: $data['lastName'] ?? '',
            email: $data['email'] ?? null,
        ));

        $all = $this->queryBus->ask(new GetAllPersonsQuery());
        return $this->json(end($all), Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        try {
            $this->commandBus->dispatch(new UpdatePersonCommand(
                id: $id,
                firstName: $data['firstName'] ?? '',
                lastName: $data['lastName'] ?? '',
                email: $data['email'] ?? null,
            ));
            return $this->json($this->queryBus->ask(new GetPersonByIdQuery($id)));
        } catch (HandlerFailedException $e) {
            if ($e->getPrevious() instanceof PersonNotFoundException) {
                return $this->json(['error' => $e->getPrevious()->getMessage()], Response::HTTP_NOT_FOUND);
            }
            throw $e;
        }
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        try {
            $this->commandBus->dispatch(new DeletePersonCommand($id));
            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (HandlerFailedException $e) {
            if ($e->getPrevious() instanceof PersonNotFoundException) {
                return $this->json(['error' => $e->getPrevious()->getMessage()], Response::HTTP_NOT_FOUND);
            }
            throw $e;
        }
    }
}
