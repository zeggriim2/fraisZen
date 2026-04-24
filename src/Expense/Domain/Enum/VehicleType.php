<?php

declare(strict_types=1);

namespace App\Expense\Domain\Enum;

enum VehicleType: string
{
    case Car        = 'car';
    case Motorcycle = 'motorcycle';
    case Moped      = 'moped';
}