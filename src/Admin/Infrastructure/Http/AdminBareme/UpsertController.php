<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminBareme;

use App\Admin\Application\Command\UpsertBaremeKilometrique\UpsertBaremeKilometriqueCommand;
use App\Admin\Application\Query\GetBaremeKilometrique\GetBaremeKilometriqueQuery;
use App\Admin\Infrastructure\Http\AbstractAdminController;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/bareme-kilometrique/{year}', name: 'upsert', requirements: ['year' => '\d{4}'], methods: [Request::METHOD_PUT])]
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
        $rates = $data['rates'] ?? null;

        if (!$this->isValidRates($rates)) {
            return $this->json(['error' => 'Invalid rates structure'], Response::HTTP_BAD_REQUEST);
        }

        $this->commandBus->dispatch(new UpsertBaremeKilometriqueCommand(year: $year, rates: $rates));

        return $this->json($this->queryBus->ask(new GetBaremeKilometriqueQuery($year)));
    }

    private function isValidRates(mixed $rates): bool
    {
        if (!is_array($rates)) {
            return false;
        }

        foreach ([3, 4, 5, 6, 7] as $cv) {
            if (!isset($rates['car'][$cv]) || !$this->isValidTranche($rates['car'][$cv])) {
                return false;
            }
        }

        foreach ([1, 3, 6] as $group) {
            if (!isset($rates['motorcycle'][$group]) || !$this->isValidTranche($rates['motorcycle'][$group])) {
                return false;
            }
        }

        if (!isset($rates['moped']) || !$this->isValidTranche($rates['moped'])) {
            return false;
        }

        if (!isset($rates['electricMultiplier']) || !is_numeric($rates['electricMultiplier']) || $rates['electricMultiplier'] < 1) {
            return false;
        }

        return true;
    }

    private function isValidTranche(mixed $tranche): bool
    {
        return is_array($tranche)
            && isset($tranche['rate1'], $tranche['rate2'], $tranche['fixed2'], $tranche['rate3'])
            && is_numeric($tranche['rate1']) && $tranche['rate1'] > 0
            && is_numeric($tranche['rate2']) && $tranche['rate2'] > 0
            && is_numeric($tranche['fixed2']) && $tranche['fixed2'] > 0
            && is_numeric($tranche['rate3']) && $tranche['rate3'] > 0;
    }
}
