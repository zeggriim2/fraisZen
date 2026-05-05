<?php

declare(strict_types=1);

namespace App\Admin\Application\Command\UpsertFiscalConfig;

final readonly class UpsertFiscalConfigCommand
{
    public function __construct(
        public int $year,
        public float $remoteWorkDailyAllowance,
        public ?float $homeMealValue,
    ) {
    }
}
