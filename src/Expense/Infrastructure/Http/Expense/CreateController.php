<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Http\Expense;

use App\Expense\Application\Command\CreateMealExpense\CreateMealExpenseCommand;
use App\Expense\Application\Command\CreateParkingExpense\CreateParkingExpenseCommand;
use App\Expense\Application\Command\CreateRemoteWorkExpense\CreateRemoteWorkExpenseCommand;
use App\Expense\Application\Command\CreateTollExpense\CreateTollExpenseCommand;
use App\Expense\Application\Command\CreateTravelExpense\CreateTravelExpenseCommand;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('', name: 'create', methods: [Request::METHOD_POST])]
final class CreateController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
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
                'meal' => new CreateMealExpenseCommand(
                    personId: $data['personId'],
                    date: $data['date'],
                    mealAmount: (float) ($data['mealAmount'] ?? 0),
                    description: $data['description'] ?? null,
                    employerTicketContribution: (float) ($data['employerTicketContribution'] ?? 0),
                    withoutReceipt: (bool) ($data['withoutReceipt'] ?? false),
                ),
                'parking' => new CreateParkingExpenseCommand(
                    personId: $data['personId'],
                    date: $data['date'],
                    amount: (float) ($data['amount'] ?? 0),
                    location: $data['location'] ?? null,
                    description: $data['description'] ?? null,
                ),
                default => throw new \InvalidArgumentException(sprintf('Unknown expense type "%s"', $type)),
            };
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $id = $this->commandBus->dispatch($command);

        return $this->json(['id' => $id], Response::HTTP_CREATED);
    }
}
