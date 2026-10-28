<?php

declare(strict_types=1);

namespace App\Tax\Domain\Service;

/** @psalm-suppress PossiblyUnusedMethod */
final class TaxComparisonResult
{
    /**
     * @param array<string, mixed>      $values
     * @param list<string>              $missing
     * @param list<string>              $rules
     * @param array<string, mixed>|null $configuration
     * @param list<string>              $warnings
     */
    public function __construct(private readonly string $status, private readonly array $values, private readonly array $missing = [], private readonly array $rules = [], private readonly ?array $configuration = null, private readonly array $warnings = [])
    {
    }

    public function status(): string
    {
        return $this->status;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['status' => $this->status, ...$this->values, 'missing' => $this->missing, 'rules' => $this->rules, 'configuration' => $this->configuration, 'warnings' => $this->warnings];
    }
}
