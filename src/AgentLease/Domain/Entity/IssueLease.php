<?php

declare(strict_types=1);

namespace App\AgentLease\Domain\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** @psalm-api */
#[ORM\Entity]
#[ORM\Table(name: 'issue_lease')]
#[ORM\UniqueConstraint(name: 'UNIQ_ISSUE_LEASE_ISSUE', columns: ['repository_full_name', 'issue_number'])]
class IssueLease
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, length: 36)]
    private string $id;

    #[ORM\Column(name: 'repository_full_name', type: Types::STRING, length: 255)]
    private string $repositoryFullName;

    #[ORM\Column(name: 'issue_number', type: Types::INTEGER)]
    private int $issueNumber;

    #[ORM\Column(name: 'token_hash', type: Types::STRING, length: 64)]
    private string $tokenHash;

    #[ORM\Column(name: 'owner_id', type: Types::STRING, length: 255)]
    private string $ownerId;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    private function __construct(
        string $id,
        string $repositoryFullName,
        int $issueNumber,
        string $tokenHash,
        string $ownerId,
        \DateTimeImmutable $expiresAt,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->repositoryFullName = $repositoryFullName;
        $this->issueNumber = $issueNumber;
        $this->tokenHash = $tokenHash;
        $this->ownerId = $ownerId;
        $this->expiresAt = $expiresAt;
        $this->createdAt = $createdAt;
    }

    public static function create(
        string $id,
        string $repositoryFullName,
        int $issueNumber,
        string $tokenHash,
        string $ownerId,
        \DateTimeImmutable $expiresAt,
        ?\DateTimeImmutable $createdAt = null,
    ): self {
        if ('' === trim($repositoryFullName) || $issueNumber < 1 || '' === trim($ownerId)
            || !preg_match('/^[a-f0-9]{64}$/', $tokenHash)
            || $expiresAt <= ($createdAt ?? new \DateTimeImmutable())
        ) {
            throw new \InvalidArgumentException('An issue lease requires a repository, positive issue number and owner.');
        }

        return new self($id, $repositoryFullName, $issueNumber, $tokenHash, $ownerId, $expiresAt, $createdAt ?? new \DateTimeImmutable());
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function repositoryFullName(): string
    {
        return $this->repositoryFullName;
    }

    public function issueNumber(): int
    {
        return $this->issueNumber;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function ownerId(): string
    {
        return $this->ownerId;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }
}
