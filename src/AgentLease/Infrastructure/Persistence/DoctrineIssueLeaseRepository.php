<?php

declare(strict_types=1);

namespace App\AgentLease\Infrastructure\Persistence;

use App\AgentLease\Domain\Repository\IssueLeaseRepositoryInterface;
use Doctrine\DBAL\Connection;

/** @psalm-api */
final readonly class DoctrineIssueLeaseRepository implements IssueLeaseRepositoryInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function acquire(string $repositoryFullName, int $issueNumber, string $tokenHash, string $ownerId, \DateTimeImmutable $expiresAt, \DateTimeImmutable $now): bool
    {
        $sql = <<<'SQL'
INSERT INTO issue_lease (id, repository_full_name, issue_number, token_hash, owner_id, expires_at, created_at)
VALUES (:id, :repository, :issue, :token_hash, :owner, :expires_at, :created_at)
AS candidate
ON DUPLICATE KEY UPDATE
    token_hash = IF(issue_lease.expires_at <= :now, candidate.token_hash, issue_lease.token_hash),
    owner_id = IF(issue_lease.expires_at <= :now, candidate.owner_id, issue_lease.owner_id),
    expires_at = IF(issue_lease.expires_at <= :now, candidate.expires_at, issue_lease.expires_at),
    created_at = IF(issue_lease.expires_at <= :now, candidate.created_at, issue_lease.created_at)
SQL;
        $this->connection->executeStatement($sql, [
            'id' => (string) \Symfony\Component\Uid\Uuid::v4(), 'repository' => $repositoryFullName, 'issue' => $issueNumber,
            'token_hash' => $tokenHash, 'owner' => $ownerId, 'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'created_at' => $now->format('Y-m-d H:i:s'), 'now' => $now->format('Y-m-d H:i:s'),
        ]);

        return 1 === $this->connection->executeQuery(
            'SELECT COUNT(*) FROM issue_lease WHERE repository_full_name = :repository AND issue_number = :issue AND token_hash = :token AND owner_id = :owner AND expires_at > :now',
            ['repository' => $repositoryFullName, 'issue' => $issueNumber, 'token' => $tokenHash, 'owner' => $ownerId, 'now' => $now->format('Y-m-d H:i:s')]
        )->fetchOne();
    }

    public function renew(string $repositoryFullName, int $issueNumber, string $ownerId, string $tokenHash, \DateTimeImmutable $expiresAt, \DateTimeImmutable $now): bool
    {
        return 1 === $this->connection->executeStatement(
            'UPDATE issue_lease SET expires_at = :expires_at WHERE repository_full_name = :repository AND issue_number = :issue AND owner_id = :owner AND token_hash = :token AND expires_at > :now',
            ['expires_at' => $expiresAt->format('Y-m-d H:i:s'), 'repository' => $repositoryFullName, 'issue' => $issueNumber, 'owner' => $ownerId, 'token' => $tokenHash, 'now' => $now->format('Y-m-d H:i:s')]
        );
    }

    public function release(string $repositoryFullName, int $issueNumber, string $ownerId, string $tokenHash, \DateTimeImmutable $now): bool
    {
        return 1 === $this->connection->executeStatement(
            'DELETE FROM issue_lease WHERE repository_full_name = :repository AND issue_number = :issue AND owner_id = :owner AND token_hash = :token AND expires_at > :now',
            ['repository' => $repositoryFullName, 'issue' => $issueNumber, 'owner' => $ownerId, 'token' => $tokenHash, 'now' => $now->format('Y-m-d H:i:s')]
        );
    }
}
