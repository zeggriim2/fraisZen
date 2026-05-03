<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminStat;

use App\Admin\Application\Stats\AdminStatsProviderInterface;
use App\Admin\Infrastructure\Http\AbstractAdminController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/stats', name: 'stats', methods: [Request::METHOD_GET])]
class StatController extends AbstractAdminController
{
    public function __construct(private readonly AdminStatsProviderInterface $statsProvider)
    {
    }

    public function __invoke(): JsonResponse
    {
        return $this->json($this->statsProvider->getStats());
    }
}
