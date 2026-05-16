<?php

declare(strict_types=1);

namespace App\Expense\Domain\Exception;

use App\SharedKernel\Domain\Exception\NotFoundException;

final class ExpenseNotFoundException extends NotFoundException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Expense with id "%s" not found.', $id));
    }
}
