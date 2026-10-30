<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminFiscal;

use App\Admin\Application\Command\UpsertFiscalConfig\UpsertFiscalConfigCommand;
use App\Admin\Application\Exception\InvalidFiscalConfigException;
use App\Admin\Application\Query\GetFiscalConfig\GetFiscalConfigQuery;
use App\Admin\Infrastructure\Http\AbstractAdminController;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Payload: `remoteWorkDailyAllowance` and `homeMealValue` (euros, unchanged) plus the optional tax
 * comparison parameters `forfaitRatePercent` (int), `forfaitMinimumCents` / `forfaitMaximumCents`
 * (integers in cents), `version`, `source` (strings) and `effectiveFrom` (`YYYY-MM-DD`). Keys that are
 * absent or `null` are treated as "not provided"; business rules live in the command handler / entity.
 */
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
        /** @var array<string, mixed> $data */
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
            forfaitRatePercent: self::optionalInt($data, 'forfaitRatePercent'),
            forfaitMinimumCents: self::optionalInt($data, 'forfaitMinimumCents'),
            forfaitMaximumCents: self::optionalInt($data, 'forfaitMaximumCents'),
            version: self::optionalString($data, 'version'),
            source: self::optionalString($data, 'source'),
            effectiveFrom: self::optionalString($data, 'effectiveFrom'),
        ));

        return $this->json($this->queryBus->ask(new GetFiscalConfigQuery($year)));
    }

    /** @param array<string, mixed> $data */
    private static function optionalInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;
        if (null === $value) {
            return null;
        }
        if (\is_int($value)) {
            return $value;
        }
        if (\is_float($value) && floor($value) === $value) {
            return (int) $value;
        }

        throw new InvalidFiscalConfigException(sprintf('Invalid "%s": an integer is expected.', $key));
    }

    /** @param array<string, mixed> $data */
    private static function optionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if (null === $value) {
            return null;
        }
        if (!\is_string($value)) {
            throw new InvalidFiscalConfigException(sprintf('Invalid "%s": a string is expected.', $key));
        }

        return $value;
    }
}
