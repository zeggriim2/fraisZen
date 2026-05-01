<?php

declare(strict_types=1);

namespace App\Expense\Domain\Entity;

use App\Expense\Domain\Enum\ExpenseType;
use App\Expense\Domain\ValueObject\ExpenseId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'expense')]
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
    #[ORM\Column(type: 'string', length: 36)]
    protected string $id;

    #[ORM\Column(type: 'string', length: 36)]
    protected string $personId;

    #[ORM\Column(type: 'date_immutable')]
    protected \DateTimeImmutable $date;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    protected ?string $description;

    #[ORM\Column(type: 'datetime_immutable')]
    protected \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
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

    protected function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    abstract public function type(): ExpenseType;

    abstract public function amount(): float;

    abstract public function toArray(): array;

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
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
