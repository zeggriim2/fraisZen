<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Http\Expense;

use App\Expense\Application\Command\BulkCreateTravelExpense\BulkCreateTravelExpenseCommand;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/bulk-travel', name: 'bulk_travel', methods: [Request::METHOD_POST])]
final class BulkTravelController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $dates = $data['dates'] ?? [];

        if (empty($dates) || !\is_array($dates)) {
            return $this->json(['error' => 'dates array is required'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['personId'])) {
            return $this->json(['error' => 'personId is required'], Response::HTTP_BAD_REQUEST);
        }

        $count = $this->commandBus->dispatch(new BulkCreateTravelExpenseCommand(
            personId: $data['personId'],
            dates: array_values(array_filter($dates, static fn ($d) => \is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d))),
            distanceKm: (float) ($data['distanceKm'] ?? 0),
            vehiclePower: isset($data['vehiclePower']) ? (int) $data['vehiclePower'] : null,
            departure: $data['departure'] ?? null,
            arrival: $data['arrival'] ?? null,
            description: $data['description'] ?? null,
            roundTrip: (bool) ($data['roundTrip'] ?? false),
            vehicleType: $data['vehicleType'] ?? 'car',
            isElectric: (bool) ($data['isElectric'] ?? false),
        ));

        return $this->json(['created' => $count], Response::HTTP_CREATED);
    }
}
