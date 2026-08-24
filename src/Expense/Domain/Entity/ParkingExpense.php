<?php

declare(strict_types=1);

namespace App\Expense\Domain\Entity;

use App\Expense\Domain\Enum\ExpenseType;
use App\Expense\Domain\ValueObject\ExpenseId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class ParkingExpense extends Expense
{
    #[ORM\Column(name: 'parking_amount', type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $parkingAmount = '0.00';

    #[ORM\Column(name: 'parking_location', type: Types::STRING, length: 255, nullable: true)]
    private ?string $location = null;

    public function __construct(
        ExpenseId $id,
        string $personId,
        \DateTimeImmutable $date,
        ?string $description,
        float $parkingAmount,
        ?string $location = null,
    ) {
        if ($parkingAmount < 0.0) {
            throw new \InvalidArgumentException('Parking amount cannot be negative.');
        }

        parent::__construct($id, $personId, $date, $description);
        $this->parkingAmount = (string) $parkingAmount;
        $this->location = $location;
    }

    public function type(): ExpenseType
    {
        return ExpenseType::Parking;
    }

    public function amount(): float
    {
        return (float) ($this->parkingAmount ?? '0');
    }

    public function parkingAmount(): float
    {
        return (float) ($this->parkingAmount ?? '0');
    }

    public function location(): ?string
    {
        return $this->location;
    }

    public function setParkingAmount(float $amount): void
    {
        if ($amount < 0.0) {
            throw new \InvalidArgumentException('Parking amount cannot be negative.');
        }

        $this->parkingAmount = (string) $amount;
        $this->touch();
    }

    public function setLocation(?string $location): void
    {
        $this->location = $location;
        $this->touch();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_merge($this->baseArray(), [
            'parkingAmount' => $this->parkingAmount(),
            'location' => $this->location,
        ]);
    }
}
