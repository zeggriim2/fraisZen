<?php

declare(strict_types=1);

namespace App\Expense\Domain\Exception;

final class ExpenseNotFoundException extends \DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Expense with id "%s" not found.', $id));
    }
}
