<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminFiscal;

use App\Admin\Application\Command\UpsertFiscalConfig\UpsertFiscalConfigCommand;
use App\Admin\Application\Query\GetFiscalConfig\GetFiscalConfigQuery;
use App\Admin\Infrastructure\Http\AbstractAdminController;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/fiscal-config/{year}', name: 'upsert', requirements: ['year' => '\d{4}'], methods: [Request::METHOD_PUT])]
class UpsertController extends AbstractAdminController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(int $year, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $allowance = $data['remoteWorkDailyAllowance'] ?? null;
        $homeMealValue = $data['homeMealValue'] ?? null;

        if (!is_numeric($allowance) || $allowance <= 0) {
            return $this->json(['error' => 'Invalid remoteWorkDailyAllowance'], Response::HTTP_BAD_REQUEST);
        }
        if (null !== $homeMealValue && (!is_numeric($homeMealValue) || $homeMealValue <= 0)) {
            return $this->json(['error' => 'Invalid homeMealValue'], Response::HTTP_BAD_REQUEST);
        }

        $this->commandBus->dispatch(new UpsertFiscalConfigCommand(
            year: $year,
            remoteWorkDailyAllowance: (float) $allowance,
            homeMealValue: null !== $homeMealValue ? (float) $homeMealValue : null,
        ));

        return $this->json($this->queryBus->ask(new GetFiscalConfigQuery($year)));
    }
}
