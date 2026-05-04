<?php

declare(strict_types=1);

namespace App\Person\Infrastructure\Http\Person;

use App\Person\Application\Query\GetPersonById\GetPersonByIdQuery;
use App\Person\Domain\Exception\PersonNotFoundException;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/{id}', name: 'show', requirements: ['id' => Requirement::UUID_V4], methods: [Request::METHOD_GET])]
class ShowController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(string $id): JsonResponse
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
}
