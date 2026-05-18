<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Http\Expense;

use App\Expense\Application\Command\DeleteExpense\DeleteExpenseCommand;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/{id}', name: 'delete', requirements: ['id' => Requirement::UUID_V4], methods: [Request::METHOD_DELETE])]
final class DeleteController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(string $id): JsonResponse
    {
        $this->commandBus->dispatch(new DeleteExpenseCommand($id));

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
