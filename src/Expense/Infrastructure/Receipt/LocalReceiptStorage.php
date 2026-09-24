<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Receipt;

use App\Expense\Application\Receipt\ReceiptStorageInterface;

/** @psalm-suppress UnusedClass Instantiated by Symfony's dependency injection container. */
final readonly class LocalReceiptStorage implements ReceiptStorageInterface
{
    private string $directory;

    public function __construct(string $shareDir)
    {
        $this->directory = rtrim($shareDir, '/').'/receipts';
    }

    public function store(string $expenseId, string $sourcePath, string $extension): string
    {
        if (!is_dir($this->directory) && !mkdir($concurrent = $this->directory, 0755, true) && !is_dir($concurrent)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $concurrent));
        }
        $this->delete($expenseId);
        $target = $this->directory.'/'.$expenseId.'.'.$extension;
        if (!rename($sourcePath, $target)) {
            throw new \RuntimeException('Unable to store receipt');
        }

        return $target;
    }

    public function delete(string $expenseId): void
    {
        foreach (glob($this->directory.'/'.$expenseId.'.*') ?: [] as $path) {
            if (is_file($path) && !unlink($path)) {
                throw new \RuntimeException('Unable to delete receipt');
            }
        }
    }

    public function find(string $expenseId): ?string
    {
        $path = (glob($this->directory.'/'.$expenseId.'.*') ?: [])[0] ?? null;

        return is_string($path) && is_file($path) ? $path : null;
    }
}
