<?php

declare(strict_types=1);

namespace App\AgentLease\Domain\Repository;

interface IssueLeaseRepositoryInterface
{
    /** Returns true only when this caller atomically acquired the issue lease. */
    public function acquire(string $repositoryFullName, int $issueNumber, string $tokenHash, string $ownerId, \DateTimeImmutable $expiresAt, \DateTimeImmutable $now): bool;

    /** Returns true only when the owner and token still own a non-expired lease. */
    public function renew(string $repositoryFullName, int $issueNumber, string $ownerId, string $tokenHash, \DateTimeImmutable $expiresAt, \DateTimeImmutable $now): bool;

    /** Returns true only when the owner and token released its lease. */
    public function release(string $repositoryFullName, int $issueNumber, string $ownerId, string $tokenHash, \DateTimeImmutable $now): bool;
}
