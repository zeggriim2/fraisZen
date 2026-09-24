<?php

declare(strict_types=1);

namespace App\Expense\Domain\Entity;

use App\Expense\Domain\Enum\ReceiptMatchStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'receipt_match')]
#[ORM\Index(name: 'IDX_RECEIPT_MATCH_LINE', columns: ['line_id'])]
class ReceiptMatch
{
    #[ORM\Id, ORM\Column(type: Types::STRING, length: 36)] private string $id;
    #[ORM\Column(name: 'line_id', type: Types::STRING, length: 36)] private string $lineId;
    #[ORM\Column(name: 'expense_id', type: Types::STRING, length: 36)] private string $expenseId;
    #[ORM\Column(type: Types::INTEGER)] private int $confidence;
    #[ORM\Column(type: Types::STRING, enumType: ReceiptMatchStatus::class)] private ReceiptMatchStatus $status;
    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)] private array $reasons;
    #[ORM\Column(name: 'reviewed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $reviewedAt = null;
    /** @param list<string> $reasons */
    public function __construct(string $lineId, string $expenseId, int $confidence, ReceiptMatchStatus $status, array $reasons)
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->lineId = $lineId;
        $this->expenseId = $expenseId;
        $this->confidence = $confidence;
        $this->status = $status;
        $this->reasons = $reasons;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function lineId(): string
    {
        return $this->lineId;
    }

    public function expenseId(): string
    {
        return $this->expenseId;
    }

    public function status(): ReceiptMatchStatus
    {
        return $this->status;
    }

    public function confirm(): void
    {
        $this->status = ReceiptMatchStatus::Confirmed;
        $this->reviewedAt = new \DateTimeImmutable();
    }

    public function reject(): void
    {
        $this->status = ReceiptMatchStatus::Rejected;
        $this->reviewedAt = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id, 'expenseId' => $this->expenseId, 'confidence' => $this->confidence, 'status' => $this->status->value, 'reasons' => $this->reasons, 'reviewedAt' => $this->reviewedAt?->format(DATE_ATOM)];
    }
}
