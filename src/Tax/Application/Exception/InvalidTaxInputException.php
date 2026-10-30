<?php

declare(strict_types=1);

namespace App\Tax\Application\Exception;

/** Rejected input (mapped to HTTP 422 by the shared exception listener). */
final class InvalidTaxInputException extends \DomainException
{
}
