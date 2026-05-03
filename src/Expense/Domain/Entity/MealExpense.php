<?php

declare(strict_types=1);

namespace App\Expense\Domain\Entity;

use App\Expense\Domain\Enum\ExpenseType;
use App\Expense\Domain\ValueObject\ExpenseId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class MealExpense extends Expense
{
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $mealAmount;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $homeMealValue;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $employerTicketContribution;

    #[ORM\Column(type: 'boolean')]
    private bool $withoutReceipt;

    public function __construct(
        ExpenseId $id,
        string $personId,
        \DateTimeImmutable $date,
        ?string $description,
        float $mealAmount,
        float $homeMealValue = 5.35,
        float $employerTicketContribution = 0.0,
        bool $withoutReceipt = false,
    ) {
        if ($mealAmount < 0.0) {
            throw new \InvalidArgumentException('Meal amount cannot be negative.');
        }
        if ($homeMealValue <= 0.0) {
            throw new \InvalidArgumentException('Home meal value must be greater than zero.');
        }
        if ($employerTicketContribution < 0.0) {
            throw new \InvalidArgumentException('Employer ticket contribution cannot be negative.');
        }

        parent::__construct($id, $personId, $date, $description);
        $this->mealAmount = (string) $mealAmount;
        $this->homeMealValue = (string) $homeMealValue;
        $this->employerTicketContribution = (string) $employerTicketContribution;
        $this->withoutReceipt = $withoutReceipt;
    }

    public function type(): ExpenseType
    {
        return ExpenseType::Meal;
    }

    public function amount(): float
    {
        if ($this->withoutReceipt) {
            return (float) $this->homeMealValue;
        }

        return max(0.0, (float) $this->mealAmount - (float) $this->homeMealValue - (float) $this->employerTicketContribution);
    }

    public function mealAmount(): float
    {
        return (float) $this->mealAmount;
    }

    public function homeMealValue(): float
    {
        return (float) $this->homeMealValue;
    }

    public function employerTicketContribution(): float
    {
        return (float) $this->employerTicketContribution;
    }

    public function withoutReceipt(): bool
    {
        return $this->withoutReceipt;
    }

    public function setMealAmount(float $amount): void
    {
        if ($amount < 0.0) {
            throw new \InvalidArgumentException('Meal amount cannot be negative.');
        }

        $this->mealAmount = (string) $amount;
        $this->touch();
    }

    public function setEmployerTicketContribution(float $amount): void
    {
        if ($amount < 0.0) {
            throw new \InvalidArgumentException('Employer ticket contribution cannot be negative.');
        }

        $this->employerTicketContribution = (string) $amount;
        $this->touch();
    }

    public function setWithoutReceipt(bool $v): void
    {
        $this->withoutReceipt = $v;
        $this->touch();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_merge($this->baseArray(), [
            'mealAmount' => $this->mealAmount(),
            'homeMealValue' => $this->homeMealValue(),
            'employerTicketContribution' => $this->employerTicketContribution(),
            'withoutReceipt' => $this->withoutReceipt(),
        ]);
    }
}
