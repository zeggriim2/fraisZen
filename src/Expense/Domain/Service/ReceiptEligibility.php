<?php

declare(strict_types=1);

namespace App\Expense\Domain\Service;

use App\Expense\Domain\Entity\Expense;
use App\Expense\Domain\Entity\MealExpense;
use App\Expense\Domain\Entity\ParkingExpense;
use App\Expense\Domain\Entity\TollExpense;

final class ReceiptEligibility
{
    public static function supports(Expense $expense): bool
    {
        return $expense instanceof TollExpense
            || $expense instanceof ParkingExpense
            || ($expense instanceof MealExpense && $expense->withoutReceipt());
    }
}
