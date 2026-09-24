<?php

declare(strict_types=1);

namespace App\Expense\Domain\Exception;

use App\SharedKernel\Domain\Exception\ConflictException;

final class DuplicateReceiptException extends ConflictException
{
    public function __construct(string $expenseId)
    {
        parent::__construct(sprintf('Ce justificatif est déjà rattaché à la dépense %s.', $expenseId));
    }
}
