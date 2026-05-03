<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Http;

use App\SharedKernel\Infrastructure\PublicHoliday\PublicHolidayService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/api/public-holidays/{year}', name: 'public_holidays', requirements: ['year' => '\d{4}'], methods: [Request::METHOD_GET])]
final class PublicHolidayController extends AbstractController
{
    public function __construct(private readonly PublicHolidayService $publicHolidayService)
    {
    }

    public function __invoke(int $year): JsonResponse
    {
        return $this->json($this->publicHolidayService->forYear($year));
    }
}
