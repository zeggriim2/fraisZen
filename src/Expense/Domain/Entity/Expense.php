<?php

declare(strict_types=1);

namespace App\Expense\Domain\Entity;

use App\Expense\Domain\Enum\ExpenseType;
use App\Expense\Domain\ValueObject\ExpenseId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'expense')]
#[ORM\Index(name: 'IDX_EXPENSE_PERSON', columns: ['person_id'])]
#[ORM\Index(name: 'IDX_EXPENSE_DATE', columns: ['date'])]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string')]
#[ORM\DiscriminatorMap([
    'travel' => TravelExpense::class,
    'remote_work' => RemoteWorkExpense::class,
    'toll' => TollExpense::class,
    'meal' => MealExpense::class,
    'parking' => ParkingExpense::class,
])]
abstract class Expense
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36)]
    protected string $id;

    #[ORM\Column(type: Types::STRING, length: 36)]
    protected string $personId;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected \DateTimeImmutable $date;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $description;

    #[ORM\Column(name: 'receipt_filename', type: Types::STRING, length: 255, nullable: true)]
    protected ?string $receiptFilename = null;

    #[ORM\Column(name: 'receipt_mime_type', type: Types::STRING, length: 100, nullable: true)]
    protected ?string $receiptMimeType = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    protected \DateTimeImmutable $updatedAt;

    public function __construct(
        ExpenseId $id,
        string $personId,
        \DateTimeImmutable $date,
        ?string $description,
    ) {
        $this->id = $id->value();
        $this->personId = $personId;
        $this->date = $date;
        $this->description = $description;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function id(): ExpenseId
    {
        return ExpenseId::fromString($this->id);
    }

    public function personId(): string
    {
        return $this->personId;
    }

    public function date(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function receiptFilename(): ?string
    {
        return $this->receiptFilename;
    }

    public function receiptMimeType(): ?string
    {
        return $this->receiptMimeType;
    }

    public function setReceipt(?string $filename, ?string $mimeType): void
    {
        $this->receiptFilename = $filename;
        $this->receiptMimeType = $mimeType;
        $this->touch();
    }

    protected function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    abstract public function type(): ExpenseType;

    abstract public function amount(): float;

    /** @return array<string, mixed> */
    abstract public function toArray(): array;

    /** @return array<string, mixed> */
    protected function baseArray(): array
    {
        return [
            'id' => $this->id,
            'personId' => $this->personId,
            'type' => $this->type()->value,
            'typeLabel' => $this->type()->label(),
            'date' => $this->date->format('Y-m-d'),
            'description' => $this->description,
            'amount' => $this->amount(),
            'receiptFilename' => $this->receiptFilename,
            'receiptMimeType' => $this->receiptMimeType,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
