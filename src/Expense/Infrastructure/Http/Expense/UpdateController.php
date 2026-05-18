<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Http\Expense;

use App\Expense\Application\Command\UpdateExpense\UpdateExpenseCommand;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/{id}', name: 'update', requirements: ['id' => Requirement::UUID_V4], methods: [Request::METHOD_PATCH])]
final class UpdateController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(string $id, Request $request): JsonResponse
    {
        $fields = json_decode($request->getContent(), true) ?? [];

        $this->commandBus->dispatch(new UpdateExpenseCommand($id, $fields));

        return $this->json(['success' => true]);
    }
}
