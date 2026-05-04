<?php

declare(strict_types=1);

namespace App\Person\Infrastructure\Http\Person;

use App\Auth\Domain\Entity\User;
use App\Person\Application\Command\CreatePerson\CreatePersonCommand;
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

    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $id = $this->commandBus->dispatch(new CreatePersonCommand(
            userId: $this->userId(),
            firstName: $data['firstName'] ?? '',
            lastName: $data['lastName'] ?? '',
            email: $data['email'] ?? null,
            favorite: (bool) ($data['favorite'] ?? false),
        ));

        return $this->json($this->queryBus->ask(new GetPersonByIdQuery($id)), Response::HTTP_CREATED);
    }

    private function userId(): string
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user->id()->value();
    }
}
