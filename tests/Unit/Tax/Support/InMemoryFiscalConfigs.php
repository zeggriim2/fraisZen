<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Support;

use App\Admin\Domain\Entity\FiscalConfig;
use App\Admin\Domain\Repository\FiscalConfigRepositoryInterface;

final class InMemoryFiscalConfigs implements FiscalConfigRepositoryInterface
{
    /** @var array<int, FiscalConfig> */
    public array $rows = [];

    public function findByYear(int $year): ?FiscalConfig
    {
        return $this->rows[$year] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->rows);
    }

    public function save(FiscalConfig $config): void
    {
        $this->rows[$config->year()] = $config;
    }
}
