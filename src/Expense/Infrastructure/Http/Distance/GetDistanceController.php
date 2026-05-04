<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Http\Distance;

use App\Expense\Application\Query\GetDistance\GetDistanceQuery;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/expenses/distance', name: 'expense_distance_get', methods: [Request::METHOD_GET])]
final class GetDistanceController extends AbstractController
{
    public function __construct(private readonly QueryBusInterface $queryBus)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $fromLat = (float) $request->query->get('fromLat', 0);
        $fromLng = (float) $request->query->get('fromLng', 0);
        $toLat   = (float) $request->query->get('toLat', 0);
        $toLng   = (float) $request->query->get('toLng', 0);

        try {
            $km = $this->queryBus->ask(new GetDistanceQuery($fromLat, $fromLng, $toLat, $toLng));

            return $this->json(['distanceKm' => $km]);
        } catch (\Throwable) {
            return $this->json(
                ['error' => 'Distance calculation unavailable'],
                Response::HTTP_SERVICE_UNAVAILABLE,
            );
        }
    }
}