<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http;

use App\Admin\Domain\Entity\FiscalConfig;
use App\Admin\Domain\Repository\FiscalConfigRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminFiscalConfigController extends AbstractController
{
    public function __construct(private readonly FiscalConfigRepositoryInterface $fiscalConfigRepository)
    {
    }

    #[Route('/fiscal-config', methods: [Request::METHOD_GET])]
    public function list(): JsonResponse
    {
        $configs = $this->fiscalConfigRepository->findAll();

        return $this->json(array_map(fn (FiscalConfig $c) => $c->toArray(), $configs));
    }

    #[Route('/fiscal-config/{year}', requirements: ['year' => '\d{4}'], methods: [Request::METHOD_GET])]
    public function get(int $year): JsonResponse
    {
        $config = $this->fiscalConfigRepository->findByYear($year);
        if (!$config) {
            return $this->json(['error' => 'No config for this year'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($config->toArray());
    }

    #[Route('/fiscal-config/{year}', requirements: ['year' => '\d{4}'], methods: [Request::METHOD_PUT])]
    public function upsert(int $year, Request $request): JsonResponse
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
