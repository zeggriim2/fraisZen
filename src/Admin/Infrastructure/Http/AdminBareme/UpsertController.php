<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminBareme;

use App\Admin\Application\Command\UpsertBaremeKilometrique\UpsertBaremeKilometriqueCommand;
use App\Admin\Application\Query\GetBaremeKilometrique\GetBaremeKilometriqueQuery;
use App\Admin\Infrastructure\Http\AbstractAdminController;
use App\Admin\Infrastructure\Http\AdminBareme\Request\UpsertBaremeRequest;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/bareme-kilometrique/{year}', name: 'upsert', requirements: ['year' => '\d{4}'], methods: [Request::METHOD_PUT])]
class UpsertController extends AbstractAdminController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(int $year, #[MapRequestPayload] UpsertBaremeRequest $dto): JsonResponse
    {
        $this->commandBus->dispatch(new UpsertBaremeKilometriqueCommand(year: $year, rates: $dto->rates->toRatesArray()));

        return $this->json($this->queryBus->ask(new GetBaremeKilometriqueQuery($year)));
    }
}
