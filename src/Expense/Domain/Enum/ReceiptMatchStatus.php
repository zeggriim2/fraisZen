<?php

declare(strict_types=1);

namespace App\Expense\Domain\Enum;

enum ReceiptMatchStatus: string
{
    case Suggested = 'suggested';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
}
