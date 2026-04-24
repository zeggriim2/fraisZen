<?php

declare(strict_types=1);

namespace App\Expense\Domain\Entity;

use App\Expense\Domain\Enum\ExpenseType;
use App\Expense\Domain\ValueObject\ExpenseId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class RemoteWorkExpense extends Expense
{
    private const DAILY_ALLOWANCE = 2.50;

    public function __construct(ExpenseId $id, string $personId, \DateTimeImmutable $date, ?string $description)
    {
        parent::__construct($id, $personId, $date, $description);
    }

    public function type(): ExpenseType { return ExpenseType::RemoteWork; }
    public function amount(): float { return self::DAILY_ALLOWANCE; }
    public function toArray(): array { return $this->baseArray(); }
}
