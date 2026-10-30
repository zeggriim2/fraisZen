<?php

declare(strict_types=1);

namespace App\Tax\Domain\Exception;

/** Only a complete ("available") calculation can be validated and closed (HTTP 422). */
final class TaxYearNotClosableException extends \DomainException
{
    public static function becauseStatus(int $year, string $status): self
    {
        return new self(\sprintf('Year %d cannot be closed while the calculation is "%s": complete the missing information first.', $year, $status));
    }
}
