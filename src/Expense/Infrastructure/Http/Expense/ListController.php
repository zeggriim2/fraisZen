<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Http\Expense;

use App\Expense\Application\Query\GetExpensesByPeriod\GetExpensesByPeriodQuery;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('', name: 'list', methods: [Request::METHOD_GET])]
final class ListController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $from = $request->query->get('from', date('Y-m-01'));
        $to = $request->query->get('to', date('Y-m-t'));
        $personId = $request->query->get('personId') ?: null;

        return $this->json($this->queryBus->ask(new GetExpensesByPeriodQuery($from, $to, $personId)));
    }
}
