<?php

declare(strict_types=1);

namespace App\Expense\Domain\Enum;

enum DeclarationTaxCase: string
{
    case DeclarantOne = '1AK';
    case DeclarantTwo = '1BK';
    case DependantOne = '1CK';
    case DependantTwo = '1DK';
}
