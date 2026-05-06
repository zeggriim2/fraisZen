<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Http;

use App\Expense\Domain\Repository\BaremeKilometriqueRepositoryInterface;
use App\Expense\Domain\Service\BaremeKilometriqueProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/baremes', name: 'bareme_')]
final class BaremeKilometriqueController extends AbstractController
{
    public function __construct(
        private readonly BaremeKilometriqueRepositoryInterface $repository,
    ) {
    }

    #[Route('/{year}', name: 'get', requirements: ['year' => '\d{4}'], methods: [Request::METHOD_GET])]
    public function get(int $year): JsonResponse
    {
        $entity = $this->repository->findByYear($year);
        $rates = null !== $entity ? $entity->rates() : BaremeKilometriqueProvider::forYear($year);

        return $this->json(['year' => $year, 'rates' => $rates]);
    }
}
