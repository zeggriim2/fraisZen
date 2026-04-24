<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Http;

use App\Expense\Application\Command\CreateRemoteWorkExpense\CreateRemoteWorkExpenseCommand;
use App\Expense\Application\Command\CreateTollExpense\CreateTollExpenseCommand;
use App\Expense\Application\Command\CreateTravelExpense\CreateTravelExpenseCommand;
use App\Expense\Application\Command\DeleteExpense\DeleteExpenseCommand;
use App\Expense\Application\Query\GetExpensesByPeriod\GetExpensesByPeriodQuery;
use App\Expense\Application\Query\GetExpensesSummary\GetExpensesSummaryQuery;
use App\Expense\Domain\Exception\ExpenseNotFoundException;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/expenses')]
final class ExpenseController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {}

    #[Route('', methods: [Request::METHOD_GET])]
    public function list(Request $request): JsonResponse
    {
        $from     = $request->query->get('from', date('Y-m-01'));
        $to       = $request->query->get('to', date('Y-m-t'));
        $personId = $request->query->get('personId') ?: null;

        return $this->json($this->queryBus->ask(new GetExpensesByPeriodQuery($from, $to, $personId)));
    }

    #[Route('/summary', methods: [Request::METHOD_GET])]
    public function summary(Request $request): JsonResponse
    {
        $personId = $request->query->get('personId', '');
        $year     = (int) $request->query->get('year', (int) date('Y'));

        if (empty($personId)) {
            return $this->json(['error' => 'personId is required'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->queryBus->ask(new GetExpensesSummaryQuery($personId, $year)));
    }

    #[Route('', methods: [Request::METHOD_POST])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $type = $data['type'] ?? '';

        try {
            $command = match ($type) {
                'travel' => new CreateTravelExpenseCommand(
                    personId: $data['personId'],
                    date: $data['date'],
                    distanceKm: (float) $data['distanceKm'],
                    vehiclePower: isset($data['vehiclePower']) ? (int) $data['vehiclePower'] : null,
                    departure: $data['departure'] ?? null,
                    arrival: $data['arrival'] ?? null,
                    description: $data['description'] ?? null,
                    roundTrip: (bool) ($data['roundTrip'] ?? false),
                    vehicleType: $data['vehicleType'] ?? 'car',
                    isElectric: (bool) ($data['isElectric'] ?? false),
                ),
                'remote_work' => new CreateRemoteWorkExpenseCommand(
                    personId: $data['personId'],
                    date: $data['date'],
                    description: $data['description'] ?? null,
                ),
                'toll' => new CreateTollExpenseCommand(
                    personId: $data['personId'],
                    date: $data['date'],
                    amount: (float) $data['amount'],
                    departure: $data['departure'] ?? null,
                    arrival: $data['arrival'] ?? null,
                    description: $data['description'] ?? null,
                ),
                default => throw new \InvalidArgumentException(sprintf('Unknown expense type "%s"', $type)),
            };
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $this->commandBus->dispatch($command);

        return $this->json(['message' => 'Expense created'], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: [Request::METHOD_DELETE])]
    public function delete(string $id): JsonResponse
    {
        try {
            $this->commandBus->dispatch(new DeleteExpenseCommand($id));
            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (HandlerFailedException $e) {
            if ($e->getPrevious() instanceof ExpenseNotFoundException) {
                return $this->json(['error' => $e->getPrevious()->getMessage()], Response::HTTP_NOT_FOUND);
            }
            throw $e;
        }
    }
}
