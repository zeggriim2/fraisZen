<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminUser;

use App\Admin\Application\Command\DeleteUser\DeleteUserCommand;
use App\Admin\Infrastructure\Http\AbstractAdminController;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/users/{id}', name: 'delete', requirements: ['id' => Requirement::UUID_V4], methods: ['DELETE'])]
class DeleteController extends AbstractAdminController
{
    public function __construct(private readonly CommandBusInterface $commandBus)
    {
    }

    public function __invoke(string $id): JsonResponse
    {
        $this->commandBus->dispatch(new DeleteUserCommand($id));

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
