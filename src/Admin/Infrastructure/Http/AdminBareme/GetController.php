<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminBareme;

use App\Admin\Application\Query\GetBaremeKilometrique\GetBaremeKilometriqueQuery;
use App\Admin\Infrastructure\Http\AbstractAdminController;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/bareme-kilometrique/{year}', name: 'get', requirements: ['year' => '\d{4}'], methods: [Request::METHOD_GET])]
class GetController extends AbstractAdminController
{
    public function __construct(private readonly QueryBusInterface $queryBus)
    {
    }

    public function __invoke(int $year): JsonResponse
    {
        $result = $this->queryBus->ask(new GetBaremeKilometriqueQuery($year));
        if (!$result) {
            return $this->json(['error' => 'No barème for this year'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($result);
    }
}
