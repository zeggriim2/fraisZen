<?php

declare(strict_types=1);

namespace App\Expense\Domain\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'receipt_line')]
#[ORM\Index(name: 'IDX_RECEIPT_LINE_DOCUMENT', columns: ['document_id', 'line_number'])]
class ReceiptLine
{
    #[ORM\Id, ORM\Column(type: Types::STRING, length: 36)] private string $id;
    #[ORM\Column(name: 'document_id', type: Types::STRING, length: 36)] private string $documentId;
    #[ORM\Column(name: 'line_number', type: Types::INTEGER)] private int $lineNumber;
    #[ORM\Column(type: Types::DATE_IMMUTABLE)] private \DateTimeImmutable $date;
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)] private ?string $departure;
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)] private ?string $arrival;
    #[ORM\Column(name: 'amount_ht', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)] private ?string $amountHt;
    #[ORM\Column(name: 'amount_ttc', type: Types::DECIMAL, precision: 10, scale: 2)] private string $amountTtc;
    #[ORM\Column(name: 'distance_km', type: Types::DECIMAL, precision: 10, scale: 1, nullable: true)] private ?string $distanceKm;
    #[ORM\Column(name: 'raw_text', type: Types::TEXT)] private string $rawText;
    public function __construct(string $documentId, int $lineNumber, \DateTimeImmutable $date, ?string $departure, ?string $arrival, ?float $amountHt, float $amountTtc, ?float $distanceKm, string $rawText)
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->documentId = $documentId;
        $this->lineNumber = $lineNumber;
        $this->date = $date;
        $this->departure = $departure;
        $this->arrival = $arrival;
        $this->amountHt = null === $amountHt ? null : (string) $amountHt;
        $this->amountTtc = (string) $amountTtc;
        $this->distanceKm = null === $distanceKm ? null : (string) $distanceKm;
        $this->rawText = $rawText;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function documentId(): string
    {
        return $this->documentId;
    }

    public function date(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function amountTtc(): float
    {
        return (float) $this->amountTtc;
    }

    public function departure(): ?string
    {
        return $this->departure;
    }

    public function arrival(): ?string
    {
        return $this->arrival;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id, 'lineNumber' => $this->lineNumber, 'date' => $this->date->format('Y-m-d'), 'departure' => $this->departure, 'arrival' => $this->arrival, 'amountHt' => null === $this->amountHt ? null : (float) $this->amountHt, 'amountTtc' => (float) $this->amountTtc, 'distanceKm' => null === $this->distanceKm ? null : (float) $this->distanceKm, 'rawText' => $this->rawText];
    }
}
