<?php

declare(strict_types=1);

namespace App\Tax\Application\Exception;

use App\SharedKernel\Domain\Exception\NotFoundException;

/** Unknown input, or input that belongs to another person: both are reported identically (no leak). */
final class TaxInputNotFoundException extends NotFoundException
{
    public static function withId(string $kind, string $id): self
    {
        return new self(sprintf('%s "%s" not found.', $kind, $id));
    }
}
