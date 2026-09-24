<?php

declare(strict_types=1);

namespace App\Expense\Application\Receipt;

interface ReceiptStorageInterface
{
    public function store(string $expenseId, string $sourcePath, string $extension): string;

    public function delete(string $expenseId): void;

    public function find(string $expenseId): ?string;
}
