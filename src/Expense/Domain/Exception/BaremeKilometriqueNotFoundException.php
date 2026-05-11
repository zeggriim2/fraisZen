<?php

declare(strict_types=1);

namespace App\Expense\Domain\Exception;

final class BaremeKilometriqueNotFoundException extends \DomainException
{
    public static function forYear(int $year): self
    {
        return new self(sprintf('Le barème kilométrique de %d n\'a pas été configuré.', $year));
    }
}
