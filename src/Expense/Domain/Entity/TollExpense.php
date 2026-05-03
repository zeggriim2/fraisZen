<?php

declare(strict_types=1);

namespace App\Expense\Domain\Entity;

use App\Expense\Domain\Enum\ExpenseType;
use App\Expense\Domain\ValueObject\ExpenseId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class TollExpense extends Expense
{
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $tollAmount;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $departure;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $arrival;

    public function __construct(
        ExpenseId $id,
        string $personId,
        \DateTimeImmutable $date,
        ?string $description,
        float $tollAmount,
        ?string $departure,
        ?string $arrival,
    ) {
        if ($tollAmount < 0.0) {
            throw new \InvalidArgumentException('Toll amount cannot be negative.');
        }

        parent::__construct($id, $personId, $date, $description);
        $this->tollAmount = (string) $tollAmount;
        $this->departure = $departure;
        $this->arrival = $arrival;
    }

    public function setTollAmount(float $amount): void
    {
        if ($amount < 0.0) {
            throw new \InvalidArgumentException('Toll amount cannot be negative.');
        }

        $this->tollAmount = (string) $amount;
        $this->touch();
    }

    public function setDeparture(?string $departure): void
    {
        $this->departure = $departure;
        $this->touch();
    }

    public function setArrival(?string $arrival): void
    {
        $this->arrival = $arrival;
        $this->touch();
    }

    public function type(): ExpenseType
    {
        return ExpenseType::Toll;
    }

    public function amount(): float
    {
        return (float) $this->tollAmount;
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
        return array_merge($this->baseArray(), [
            'tollAmount' => $this->amount(),
            'departure' => $this->departure,
            'arrival' => $this->arrival,
        ]);
    }
}
