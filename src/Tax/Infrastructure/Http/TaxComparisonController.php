<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\Http;

use App\Auth\Domain\Entity\User;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use App\SharedKernel\Infrastructure\Security\OwnershipGuardInterface;
use App\Tax\Application\Query\GetTaxComparison\GetTaxComparisonQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Forfait / real expenses comparison of one person of the current user, for one year.
 * The household dashboard calls this endpoint once per person: results are never aggregated.
 */
#[Route('/api/persons/{personId}/tax-comparison/{year}', name: 'tax_comparison_get', requirements: ['personId' => Requirement::UUID_V4, 'year' => '\d{4}'], methods: [Request::METHOD_GET])]
final class TaxComparisonController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly OwnershipGuardInterface $ownershipGuard,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(string $personId, int $year): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        if ($year < 1900 || $year > 2999) {
            return $this->json(['error' => 'Invalid year'], Response::HTTP_BAD_REQUEST);
        }
        $this->ownershipGuard->assertPersonBelongsToUser($personId, $user->id()->value());

        return $this->json($this->queryBus->ask(new GetTaxComparisonQuery($personId, $year, $this->clock->now())));
    }
}
