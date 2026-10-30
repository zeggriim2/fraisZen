<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\Http;

use App\Auth\Domain\Entity\User;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use App\SharedKernel\Infrastructure\Security\OwnershipGuardInterface;
use App\Tax\Application\Command\CloseTaxYear\CloseTaxYearCommand;
use App\Tax\Application\Command\ReopenTaxYear\ReopenTaxYearCommand;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Validation, closing and explicit reopening of the comparison of ONE person for ONE year.
 *
 * Authorization follows the existing conventions of the person-owned endpoints: an authenticated
 * user (JWT firewall, subscription middleware on /api) who owns the person (OwnershipGuard, 404
 * otherwise). Both actions require an explicit confirmation in the payload.
 */
#[Route('/api/persons/{personId}/tax-comparison/{year}/closure', name: 'tax_closure_', requirements: ['personId' => Requirement::UUID_V4, 'year' => '\d{4}'])]
final class TaxYearClosureController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly OwnershipGuardInterface $ownershipGuard,
        private readonly ClockInterface $clock,
    ) {
    }

    /** Body: {"confirm": true, "calculationFingerprint": "<fingerprint of the reviewed calculation>"} */
    #[Route('', name: 'close', methods: [Request::METHOD_POST])]
    public function close(string $personId, int $year, Request $request): JsonResponse
    {
        $user = $this->authorize($personId);
        $payload = $this->payload($request);
        if (null === $payload) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }
        if ($year < 1900 || $year > 2999) {
            return $this->json(['error' => 'Invalid year'], Response::HTTP_BAD_REQUEST);
        }
        $fingerprint = $payload['calculationFingerprint'] ?? null;
        if (true !== ($payload['confirm'] ?? null) || !\is_string($fingerprint) || 1 !== preg_match('/^[a-f0-9]{64}$/', $fingerprint)) {
            return $this->json(['error' => 'An explicit confirmation and the fingerprint of the reviewed calculation are required'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(
            $this->commandBus->dispatch(new CloseTaxYearCommand($personId, $year, $user->id()->value(), $fingerprint, $this->clock->now())),
            Response::HTTP_CREATED,
        );
    }

    /** Body: {"confirm": true, "version": <closure version in force>} */
    #[Route('/reopen', name: 'reopen', methods: [Request::METHOD_POST])]
    public function reopen(string $personId, int $year, Request $request): JsonResponse
    {
        $user = $this->authorize($personId);
        $payload = $this->payload($request);
        if (null === $payload) {
            return $this->json(['error' => 'Invalid JSON body'], Response::HTTP_BAD_REQUEST);
        }
        if ($year < 1900 || $year > 2999) {
            return $this->json(['error' => 'Invalid year'], Response::HTTP_BAD_REQUEST);
        }
        $version = $payload['version'] ?? null;
        if (true !== ($payload['confirm'] ?? null) || !\is_int($version) || $version < 1) {
            return $this->json(['error' => 'An explicit confirmation and the closure version are required'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->commandBus->dispatch(new ReopenTaxYearCommand($personId, $year, $user->id()->value(), $version, $this->clock->now())));
    }

    private function authorize(string $personId): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        $this->ownershipGuard->assertPersonBelongsToUser($personId, $user->id()->value());

        return $user;
    }

    /** @return array<array-key, mixed>|null */
    private function payload(Request $request): ?array
    {
        try {
            $payload = json_decode($request->getContent(), true, 8, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return \is_array($payload) ? $payload : null;
    }
}
