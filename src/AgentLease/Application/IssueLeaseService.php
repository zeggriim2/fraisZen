<?php

declare(strict_types=1);

namespace App\AgentLease\Application;

use App\AgentLease\Domain\Repository\IssueLeaseRepositoryInterface;

/** @psalm-api */
final readonly class IssueLeaseService
{
    public function __construct(
        private IssueLeaseRepositoryInterface $repository,
        private int $durationSeconds = 900,
    ) {
        if ($durationSeconds < 1) {
            throw new \InvalidArgumentException('Lease duration must be positive.');
        }
    }

    /** @return array{token: string, expiresAt: \DateTimeImmutable}|null */
    public function acquire(string $repositoryFullName, int $issueNumber, string $ownerId, ?\DateTimeImmutable $now = null): ?array
    {
        $now ??= new \DateTimeImmutable();
        $token = self::newToken();
        $expiresAt = $now->modify(sprintf('+%d seconds', $this->durationSeconds));

        if (!$this->repository->acquire($repositoryFullName, $issueNumber, self::hash($token), $ownerId, $expiresAt, $now)) {
            return null;
        }

        return ['token' => $token, 'expiresAt' => $expiresAt];
    }

    public function renew(string $repositoryFullName, int $issueNumber, string $ownerId, string $token, ?\DateTimeImmutable $now = null): ?\DateTimeImmutable
    {
        $now ??= new \DateTimeImmutable();
        $expiresAt = $now->modify(sprintf('+%d seconds', $this->durationSeconds));

        return $this->repository->renew($repositoryFullName, $issueNumber, $ownerId, self::hash($token), $expiresAt, $now) ? $expiresAt : null;
    }

    public function release(string $repositoryFullName, int $issueNumber, string $ownerId, string $token, ?\DateTimeImmutable $now = null): bool
    {
        return $this->repository->release($repositoryFullName, $issueNumber, $ownerId, self::hash($token), $now ?? new \DateTimeImmutable());
    }

    private static function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
