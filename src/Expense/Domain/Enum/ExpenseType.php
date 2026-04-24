<?php

declare(strict_types=1);

namespace App\Expense\Domain\Enum;

enum ExpenseType: string
{
    case Travel = 'travel';
    case RemoteWork = 'remote_work';
    case Toll = 'toll';

    public function label(): string
    {
        return match ($this) {
            self::Travel => 'Trajet',
            self::RemoteWork => 'Télétravail',
            self::Toll => 'Péage',
        };
    }
}
