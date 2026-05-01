<?php

declare(strict_types=1);

namespace App\Expense\Domain\Entity;

use App\Expense\Domain\Enum\ExpenseType;
use App\Expense\Domain\ValueObject\ExpenseId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ParkingExpense extends Expense
{
    #[ORM\Column(name: 'parking_amount', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private float $parkingAmount = 0.0;

    #[ORM\Column(name: 'parking_location', type: 'string', length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(name: 'receipt_filename', type: 'string', length: 255, nullable: true)]
    private ?string $receiptFilename = null;

    public function __construct(
        ExpenseId $id,
        string $personId,
        \DateTimeImmutable $date,
        ?string $description,
        float $parkingAmount,
        ?string $location = null,
    ) {
        parent::__construct($id, $personId, $date, $description);
        $this->parkingAmount = $parkingAmount;
        $this->location = $location;
        $this->receiptFilename = null;
    }

    public function type(): ExpenseType
    {
        return ExpenseType::Parking;
    }

    public function amount(): float
    {
        return (float) $this->parkingAmount;
    }

    public function parkingAmount(): float
    {
        return (float) $this->parkingAmount;
    }

    public function location(): ?string
    {
        return $this->location;
    }

    public function receiptFilename(): ?string
    {
        return $this->receiptFilename;
    }

    public function setParkingAmount(float $amount): void
    {
        $this->parkingAmount = $amount;
        $this->touch();
    }

    public function setLocation(?string $location): void
    {
        $this->location = $location;
        $this->touch();
    }

    public function setReceiptFilename(?string $filename): void
    {
        $this->receiptFilename = $filename;
        $this->touch();
    }

    public function toArray(): array
    {
        return array_merge($this->baseArray(), [
            'parkingAmount' => $this->parkingAmount(),
            'location' => $this->location,
            'receiptFilename' => $this->receiptFilename,
        ]);
    }
}
