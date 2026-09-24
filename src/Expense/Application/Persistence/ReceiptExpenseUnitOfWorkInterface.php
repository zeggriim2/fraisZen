<?php

declare(strict_types=1);

namespace App\Expense\Application\Persistence;

use App\Expense\Domain\Entity\Expense;
use App\Expense\Domain\Entity\ReceiptMatch;

interface ReceiptExpenseUnitOfWorkInterface
{
    public function persist(Expense $expense, ReceiptMatch $match): void;

    public function flush(): void;
}
