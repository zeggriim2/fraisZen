<?php

declare(strict_types=1);

namespace App\AgentLease\Infrastructure\Http;

use App\AgentLease\Application\IssueLeaseService;
use App\Auth\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** @psalm-api */
#[Route('/api/agent/leases')]
final class IssueLeaseController extends AbstractController
{
    public function __construct(private readonly IssueLeaseService $leases)
    {
    }

    #[Route('', methods: [Request::METHOD_POST])]
    public function acquire(Request $request): JsonResponse
    {
        $data = $this->payload($request);
        $lease = $this->leases->acquire((string) ($data['repository'] ?? ''), (int) ($data['issue'] ?? 0), $this->ownerId());

        if (null === $lease) {
            return $this->json(['error' => 'Issue is already leased.'], Response::HTTP_CONFLICT);
        }

        return $this->json(['token' => $lease['token'], 'expiresAt' => $lease['expiresAt']->format(DATE_ATOM)], Response::HTTP_CREATED);
    }

    #[Route('', methods: [Request::METHOD_PATCH])]
    public function renew(Request $request): JsonResponse
    {
        $data = $this->payload($request);
        $expiresAt = $this->leases->renew((string) ($data['repository'] ?? ''), (int) ($data['issue'] ?? 0), $this->ownerId(), (string) ($data['token'] ?? ''));

        return null === $expiresAt
            ? $this->json(['error' => 'Lease is missing, expired, or not owned by this token.'], Response::HTTP_CONFLICT)
            : $this->json(['expiresAt' => $expiresAt->format(DATE_ATOM)]);
    }

    #[Route('', methods: [Request::METHOD_DELETE])]
    public function release(Request $request): Response
    {
        $data = $this->payload($request);
        $released = $this->leases->release((string) ($data['repository'] ?? ''), (int) ($data['issue'] ?? 0), $this->ownerId(), (string) ($data['token'] ?? ''));

        return $released ? new Response(status: Response::HTTP_NO_CONTENT) : $this->json(['error' => 'Lease is missing, expired, or not owned by this token.'], Response::HTTP_CONFLICT);
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        return is_array($payload) ? $payload : [];
    }

    private function ownerId(): string
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user->id()->value();
    }
}
