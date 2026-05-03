<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminFiscal;

use App\Admin\Domain\Repository\FiscalConfigRepositoryInterface;
use App\Admin\Infrastructure\Http\AbstractAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/fiscal-config/{year}', name: 'get', requirements: ['year' => '\d{4}'], methods: [Request::METHOD_GET])]
class GetController extends AbstractAdminController
{
    public function __construct(private readonly FiscalConfigRepositoryInterface $fiscalConfigRepository)
    {
    }

    public function __invoke(int $year): JsonResponse
    {
        $config = $this->fiscalConfigRepository->findByYear($year);
        if (!$config) {
            return $this->json(['error' => 'No config for this year'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($config->toArray());
    }
}
