<?php

declare(strict_types=1);

namespace App\Expense\Domain\Entity;

use App\Expense\Domain\Enum\ExpenseType;
use App\Expense\Domain\ValueObject\ExpenseId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class RemoteWorkExpense extends Expense
{
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private float $dailyAllowance;

    public function __construct(ExpenseId $id, string $personId, \DateTimeImmutable $date, ?string $description, float $dailyAllowance = 2.70)
    {
        parent::__construct($id, $personId, $date, $description);
        $this->dailyAllowance = $dailyAllowance;
    }

    public function type(): ExpenseType { return ExpenseType::RemoteWork; }
    public function amount(): float { return (float) $this->dailyAllowance; }
    public function toArray(): array { return $this->baseArray(); }
}
