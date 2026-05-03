<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminFiscal;

use App\Admin\Domain\Entity\FiscalConfig;
use App\Admin\Domain\Repository\FiscalConfigRepositoryInterface;
use App\Admin\Infrastructure\Http\AbstractAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/fiscal-config', name: 'list', methods: [Request::METHOD_GET])]
class ListController extends AbstractAdminController
{
    public function __construct(private readonly FiscalConfigRepositoryInterface $fiscalConfigRepository)
    {
    }

    public function __invoke(): JsonResponse
    {
        $configs = $this->fiscalConfigRepository->findAll();

        return $this->json(array_map(fn (FiscalConfig $c) => $c->toArray(), $configs));
    }
}
