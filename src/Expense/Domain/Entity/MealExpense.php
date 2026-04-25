<?php

declare(strict_types=1);

namespace App\Expense\Domain\Entity;

use App\Expense\Domain\Enum\ExpenseType;
use App\Expense\Domain\ValueObject\ExpenseId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class MealExpense extends Expense
{
    /** Daily home meal value deducted from the actual cost (2024 rate). */
    private const HOME_MEAL_VALUE = 5.35;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private float $mealAmount;

    public function __construct(
        ExpenseId $id,
        string $personId,
        \DateTimeImmutable $date,
        ?string $description,
        float $mealAmount,
    ) {
        parent::__construct($id, $personId, $date, $description);
        $this->mealAmount = $mealAmount;
    }

    public function type(): ExpenseType { return ExpenseType::Meal; }

    /** Deductible amount = actual cost − home meal value (min 0). */
    public function amount(): float { return max(0.0, (float) $this->mealAmount - self::HOME_MEAL_VALUE); }

    public function mealAmount(): float { return (float) $this->mealAmount; }

    public function setMealAmount(float $amount): void { $this->mealAmount = $amount; }

    public function toArray(): array
    {
        return array_merge($this->baseArray(), [
            'mealAmount'     => $this->mealAmount(),
            'homeMealValue'  => self::HOME_MEAL_VALUE,
        ]);
    }
}
