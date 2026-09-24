<?php

declare(strict_types=1);

namespace App\Expense\Domain\Enum;

enum ReceiptAnalysisStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
