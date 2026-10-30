<?php

declare(strict_types=1);

namespace App\Tax\Domain\Entity;

use App\Tax\Domain\Exception\TaxYearClosureConflictException;
use App\Tax\Domain\Exception\TaxYearNotClosableException;
use App\Tax\Domain\Service\TaxComparisonResult;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Closing of the forfait / real expenses comparison of ONE person for ONE year.
 *
 * A closure freezes an immutable snapshot of the calculation, the retained data, the fiscal rules
 * (with their version, source and effective date) and the closing date. The snapshot has no mutator:
 * reading a closed year never depends on the current data or fiscal configuration.
 *
 * Reopening is explicit and never deletes anything: the closure is only marked as reopened (who and
 * when) and stays in the history. Closing again creates a NEW version; versions are unique per
 * person and year (database constraint), so two concurrent closings cannot both succeed. The
 * `revision` column is a Doctrine optimistic lock protecting concurrent reopenings.
 *
 * @psalm-type Snapshot = array{
 *     schemaVersion: int,
 *     closedAt: string,
 *     fingerprint: string,
 *     calculation: array<string, mixed>,
 *     origins: array<string, mixed>,
 *     retainedData: array<string, mixed>,
 *     fiscalRules: array{configuration: mixed, rules: mixed}
 * }
 */
#[ORM\Entity]
#[ORM\Table(name: 'tax_year_closure')]
#[ORM\UniqueConstraint(name: 'UNIQ_TAX_YEAR_CLOSURE_PERSON_YEAR_VERSION', columns: ['person_id', 'year', 'version'])]
#[ORM\Index(name: 'IDX_TAX_YEAR_CLOSURE_PERSON_YEAR', columns: ['person_id', 'year'])]
class TaxYearClosure
{
    public const SNAPSHOT_SCHEMA_VERSION = 1;

    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $id;

    #[ORM\Column(name: 'person_id', type: Types::STRING, length: 36)]
    private string $personId;

    #[ORM\Column(type: Types::INTEGER)]
    private int $year;

    #[ORM\Column(type: Types::INTEGER)]
    private int $version;

    #[ORM\Column(name: 'closed_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $closedAt;

    #[ORM\Column(name: 'closed_by', type: Types::STRING, length: 36)]
    private string $closedBy;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $fingerprint;

    /** @var Snapshot */
    #[ORM\Column(type: Types::JSON)]
    private array $snapshot;

    #[ORM\Column(name: 'reopened_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reopenedAt = null;

    #[ORM\Column(name: 'reopened_by', type: Types::STRING, length: 36, nullable: true)]
    private ?string $reopenedBy = null;

    /** Optimistic lock revision, incremented by Doctrine on every update. */
    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $revision = 1;

    /** @param Snapshot $snapshot */
    private function __construct(string $id, string $personId, int $year, int $version, \DateTimeImmutable $closedAt, string $closedBy, string $fingerprint, array $snapshot)
    {
        $this->id = $id;
        $this->personId = $personId;
        $this->year = $year;
        $this->version = $version;
        $this->closedAt = $closedAt;
        $this->closedBy = $closedBy;
        $this->fingerprint = $fingerprint;
        $this->snapshot = $snapshot;
    }

    /**
     * @param array<string, mixed> $origins      what the calculation was based on (counts, employers…)
     * @param array<string, mixed> $retainedData every input retained by the calculation
     */
    public static function close(
        string $id,
        string $personId,
        int $year,
        int $version,
        string $closedBy,
        \DateTimeImmutable $closedAt,
        TaxComparisonResult $result,
        array $origins,
        array $retainedData,
        string $fingerprint,
    ): self {
        if ($result->personId() !== $personId || $result->year() !== $year) {
            throw new \InvalidArgumentException('The calculation does not belong to the closed person and year.');
        }
        if (TaxComparisonResult::STATUS_AVAILABLE !== $result->status()) {
            throw TaxYearNotClosableException::becauseStatus($year, $result->status());
        }
        if ($version < 1) {
            throw new \InvalidArgumentException('A closure version starts at 1.');
        }
        if (1 !== preg_match('/^[a-f0-9]{64}$/', $fingerprint)) {
            throw new \InvalidArgumentException('Invalid calculation fingerprint.');
        }

        return new self($id, $personId, $year, $version, $closedAt, $closedBy, $fingerprint, [
            'schemaVersion' => self::SNAPSHOT_SCHEMA_VERSION,
            'closedAt' => $closedAt->format(\DATE_ATOM),
            'fingerprint' => $fingerprint,
            'calculation' => $result->toArray(),
            'origins' => $origins,
            'retainedData' => $retainedData,
            'fiscalRules' => ['configuration' => $result->configuration(), 'rules' => $result->rules()],
        ]);
    }

    /** Explicit reopening: the snapshot is kept untouched in the history. */
    public function reopen(string $userId, \DateTimeImmutable $at): void
    {
        if (null !== $this->reopenedAt) {
            throw TaxYearClosureConflictException::notClosed($this->year);
        }
        $this->reopenedAt = $at;
        $this->reopenedBy = $userId;
    }

    public function isClosed(): bool
    {
        return null === $this->reopenedAt;
    }

    /** The closed calculation, rebuilt from the snapshot only (never from the current configuration). */
    public function closedResult(): TaxComparisonResult
    {
        return TaxComparisonResult::fromArray($this->snapshot['calculation']);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function personId(): string
    {
        return $this->personId;
    }

    public function year(): int
    {
        return $this->year;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function closedAt(): \DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function closedBy(): string
    {
        return $this->closedBy;
    }

    public function fingerprint(): string
    {
        return $this->fingerprint;
    }

    /** @return Snapshot */
    public function snapshot(): array
    {
        return $this->snapshot;
    }

    public function reopenedAt(): ?\DateTimeImmutable
    {
        return $this->reopenedAt;
    }

    public function reopenedBy(): ?string
    {
        return $this->reopenedBy;
    }

    public function revision(): int
    {
        return $this->revision;
    }

    /** @return array{personId: string, year: int, version: int, state: 'closed'|'reopened', closedAt: string, reopenedAt: string|null} */
    public function summary(): array
    {
        return [
            'personId' => $this->personId,
            'year' => $this->year,
            'version' => $this->version,
            'state' => $this->isClosed() ? 'closed' : 'reopened',
            'closedAt' => $this->closedAt->format(\DATE_ATOM),
            'reopenedAt' => $this->reopenedAt?->format(\DATE_ATOM),
        ];
    }
}
