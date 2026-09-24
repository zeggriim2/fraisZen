<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Http;

use App\Auth\Domain\Entity\User;
use App\Expense\Application\Query\GetReceiptVault\GetReceiptVaultQuery;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use App\SharedKernel\Infrastructure\Security\OwnershipGuardInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ReceiptVaultController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly OwnershipGuardInterface $ownershipGuard,
    ) {
    }

    #[Route('/api/receipts', name: 'receipt_vault', methods: [Request::METHOD_GET])]
    public function __invoke(Request $request): JsonResponse
    {
        $personId = $request->query->getString('personId');
        $year = $request->query->getInt('year', (int) date('Y'));
        $this->ownershipGuard->assertPersonBelongsToUser($personId, $this->currentUserId());

        return $this->json($this->queryBus->ask(new GetReceiptVaultQuery($personId, $year, $request->query->getInt('page', 1), $request->query->getInt('pageSize', 6), $request->query->getString('status', 'all'), $request->query->getString('search'))));
    }

    private function currentUserId(): string
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user->id()->value();
    }
}
