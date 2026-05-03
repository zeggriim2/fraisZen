<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminFiscal;

use App\Admin\Domain\Entity\FiscalConfig;
use App\Admin\Domain\Repository\FiscalConfigRepositoryInterface;
use App\Admin\Infrastructure\Http\AbstractAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


#[Route('/fiscal-config/{year}', name: 'upsert', requirements: ['year' => '\d{4}'], methods: [Request::METHOD_PUT])]
class UpsertController extends AbstractAdminController
{
    public function __construct(private readonly FiscalConfigRepositoryInterface $fiscalConfigRepository)
    {
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

        $config = $this->fiscalConfigRepository->findByYear($year);
        if ($config) {
            $config->setRemoteWorkDailyAllowance((float) $allowance);
            if (null !== $homeMealValue) {
                $config->setHomeMealValue((float) $homeMealValue);
            }
        } else {
            $config = new FiscalConfig($year, (string) (float) $allowance, $homeMealValue ? (string) (float) $homeMealValue : '5.35');
        }

        $this->fiscalConfigRepository->save($config);

        return $this->json($config->toArray());
    }
}
