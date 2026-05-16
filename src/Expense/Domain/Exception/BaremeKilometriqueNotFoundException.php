<?php

declare(strict_types=1);

namespace App\Expense\Domain\Exception;

use App\SharedKernel\Domain\Exception\NotFoundException;

final class BaremeKilometriqueNotFoundException extends NotFoundException
{
    public static function forYear(int $year): self
    {
        return new self(sprintf('Le barème kilométrique de %d n\'a pas été configuré.', $year));
    }
}
