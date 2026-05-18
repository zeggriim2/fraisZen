<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Http\FiscalConfig;

use App\Admin\Domain\Repository\FiscalConfigRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{year}', name: 'get', requirements: ['year' => '\d{4}'], methods: [Request::METHOD_GET])]
final class GetController extends AbstractController
{
    private const FALLBACK_HOME_MEAL_VALUE = 5.35;
    private const FALLBACK_DAILY_ALLOWANCE = 2.70;

    public function __construct(
        private readonly FiscalConfigRepositoryInterface $fiscalConfigRepository,
    ) {
    }

    public function __invoke(int $year): JsonResponse
    {
        $config = $this->fiscalConfigRepository->findByYear($year);

        return $this->json([
            'year' => $year,
            'remoteWorkDailyAllowance' => $config?->remoteWorkDailyAllowance() ?? self::FALLBACK_DAILY_ALLOWANCE,
            'homeMealValue' => $config?->homeMealValue() ?? self::FALLBACK_HOME_MEAL_VALUE,
        ]);
    }
}
