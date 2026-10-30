<?php

declare(strict_types=1);

namespace App\Admin\Application\Exception;

/**
 * Rejected fiscal configuration input (domain invariant of FiscalConfig or malformed field).
 * Mapped to HTTP 422 by the shared ExceptionListener; nothing is persisted when it is thrown.
 */
final class InvalidFiscalConfigException extends \DomainException
{
}
