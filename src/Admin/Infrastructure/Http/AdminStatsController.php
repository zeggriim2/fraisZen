<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http;

use App\Admin\Application\Stats\AdminStatsProviderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminStatsController extends AbstractController
{
    public function __construct(private readonly AdminStatsProviderInterface $statsProvider)
    {
    }

    #[Route('/stats', methods: [Request::METHOD_GET])]
    public function stats(): JsonResponse
    {
        return $this->json($this->statsProvider->getStats());
    }
}
