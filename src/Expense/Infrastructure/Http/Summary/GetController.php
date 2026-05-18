<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Http\Summary;

use App\Expense\Application\Query\GetExpensesSummary\GetExpensesSummaryQuery;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('', name: 'get', methods: [Request::METHOD_GET])]
final class GetController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $personId = $request->query->get('personId', '');
        $year = (int) $request->query->get('year', (int) date('Y'));

        if (empty($personId)) {
            return $this->json(['error' => 'personId is required'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->queryBus->ask(new GetExpensesSummaryQuery($personId, $year)));
    }
}
