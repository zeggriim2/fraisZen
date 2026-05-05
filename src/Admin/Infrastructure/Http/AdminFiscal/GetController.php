<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminFiscal;

use App\Admin\Application\Query\GetFiscalConfig\GetFiscalConfigQuery;
use App\Admin\Infrastructure\Http\AbstractAdminController;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/fiscal-config/{year}', name: 'get', requirements: ['year' => '\d{4}'], methods: [Request::METHOD_GET])]
class GetController extends AbstractAdminController
{
    public function __construct(private readonly QueryBusInterface $queryBus)
    {
    }

    public function __invoke(int $year): JsonResponse
    {
        $result = $this->queryBus->ask(new GetFiscalConfigQuery($year));
        if (!$result) {
            return $this->json(['error' => 'No config for this year'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($result);
    }
}
