<?php

declare(strict_types=1);

namespace App\Admin\Domain\Repository;

use App\Admin\Domain\Entity\FiscalConfig;

interface FiscalConfigRepositoryInterface
{
    public function findByYear(int $year): ?FiscalConfig;

    /** @return FiscalConfig[] */
    public function findAll(): array;

    public function save(FiscalConfig $config): void;
}