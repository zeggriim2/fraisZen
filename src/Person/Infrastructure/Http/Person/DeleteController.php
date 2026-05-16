<?php

declare(strict_types=1);

namespace App\Person\Infrastructure\Http\Person;

use App\Person\Application\Command\DeletePerson\DeletePersonCommand;
use App\SharedKernel\Application\Bus\CommandBusInterface;
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
    ) {
    }

    public function __invoke(string $id): JsonResponse
    {
        $this->commandBus->dispatch(new DeletePersonCommand($id));

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
