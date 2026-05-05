<?php

declare(strict_types=1);

namespace App\Admin\Application\Query\GetFiscalConfig;

final readonly class GetFiscalConfigQuery
{
    public function __construct(public int $year)
    {
    }
}
