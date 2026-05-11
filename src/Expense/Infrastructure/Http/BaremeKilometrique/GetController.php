<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Http\BaremeKilometrique;

use App\Expense\Application\Query\GetBaremeKilometriqueQuery\GetBaremeKilometriqueQuery;
use App\Expense\Domain\Exception\BaremeKilometriqueNotFoundException;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/baremes/{year}', name: 'bareme_get', requirements: ['year' => '\d{4}'], methods: [Request::METHOD_GET])]
#[IsGranted('ROLE_USER')]
final class GetController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(int $year): JsonResponse
    {
        try {
            $rates = $this->queryBus->ask(new GetBaremeKilometriqueQuery($year));
        } catch (BaremeKilometriqueNotFoundException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['year' => $year, 'rates' => $rates]);
    }
}
