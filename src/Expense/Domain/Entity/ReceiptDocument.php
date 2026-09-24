<?php

declare(strict_types=1);

namespace App\Expense\Domain\Entity;

use App\Expense\Domain\Enum\ReceiptAnalysisStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'receipt_document')]
#[ORM\Index(name: 'IDX_RECEIPT_DOCUMENT_PERSON', columns: ['person_id', 'created_at'])]
#[ORM\UniqueConstraint(name: 'UNIQ_RECEIPT_DOCUMENT_SHA', columns: ['person_id', 'sha256'])]
class ReceiptDocument
{
    #[ORM\Id, ORM\Column(type: Types::STRING, length: 36)] private string $id;
    #[ORM\Column(name: 'person_id', type: Types::STRING, length: 36)] private string $personId;
    #[ORM\Column(type: Types::STRING, length: 255)] private string $filename;
    #[ORM\Column(name: 'mime_type', type: Types::STRING, length: 100)] private string $mimeType;
    #[ORM\Column(type: Types::STRING, length: 64)] private string $sha256;
    #[ORM\Column(type: Types::STRING, enumType: ReceiptAnalysisStatus::class)] private ReceiptAnalysisStatus $status;
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)] private ?string $supplier = null;
    #[ORM\Column(name: 'invoice_number', type: Types::STRING, length: 100, nullable: true)] private ?string $invoiceNumber = null;
    #[ORM\Column(name: 'invoice_date', type: Types::DATE_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $invoiceDate = null;
    #[ORM\Column(name: 'period_start', type: Types::DATE_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $periodStart = null;
    #[ORM\Column(name: 'period_end', type: Types::DATE_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $periodEnd = null;
    #[ORM\Column(name: 'total_amount', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)] private ?string $totalAmount = null;
    #[ORM\Column(name: 'error_message', type: Types::TEXT, nullable: true)] private ?string $errorMessage = null;
    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)] private \DateTimeImmutable $createdAt;
    #[ORM\Column(name: 'analyzed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $analyzedAt = null;
    public function __construct(string $personId, string $filename, string $mimeType, string $sha256)
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->personId = $personId;
        $this->filename = $filename;
        $this->mimeType = $mimeType;
        $this->sha256 = $sha256;
        $this->status = ReceiptAnalysisStatus::Pending;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): string
    {
        return $this->id;
    }

    public function personId(): string
    {
        return $this->personId;
    }

    public function filename(): string
    {
        return $this->filename;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function sha256(): string
    {
        return $this->sha256;
    }

    public function status(): ReceiptAnalysisStatus
    {
        return $this->status;
    }

    public function periodStart(): ?\DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function periodEnd(): ?\DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function startAnalysis(): void
    {
        $this->status = ReceiptAnalysisStatus::Processing;
        $this->errorMessage = null;
    }

    /** @param array<string, mixed> $metadata */
    public function completeAnalysis(array $metadata): void
    {
        $this->supplier = $metadata['supplier'] ?? null;
        $this->invoiceNumber = $metadata['invoiceNumber'] ?? null;
        $this->invoiceDate = $metadata['invoiceDate'] ?? null;
        $this->periodStart = $metadata['periodStart'] ?? null;
        $this->periodEnd = $metadata['periodEnd'] ?? null;
        $this->totalAmount = isset($metadata['totalAmount']) ? (string) $metadata['totalAmount'] : null;
        $this->status = ReceiptAnalysisStatus::Completed;
        $this->analyzedAt = new \DateTimeImmutable();
    }

    public function failAnalysis(string $message): void
    {
        $this->status = ReceiptAnalysisStatus::Failed;
        $this->errorMessage = mb_substr($message, 0, 2000);
        $this->analyzedAt = new \DateTimeImmutable();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id, 'personId' => $this->personId, 'filename' => $this->filename, 'mimeType' => $this->mimeType,
            'status' => $this->status->value, 'supplier' => $this->supplier, 'invoiceNumber' => $this->invoiceNumber,
            'invoiceDate' => $this->invoiceDate?->format('Y-m-d'), 'periodStart' => $this->periodStart?->format('Y-m-d'),
            'periodEnd' => $this->periodEnd?->format('Y-m-d'), 'totalAmount' => null === $this->totalAmount ? null : (float) $this->totalAmount,
            'errorMessage' => $this->errorMessage, 'createdAt' => $this->createdAt->format(DATE_ATOM), 'analyzedAt' => $this->analyzedAt?->format(DATE_ATOM)];
    }
}
