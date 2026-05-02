<?php

declare(strict_types=1);

namespace App\Admin\Application\Stats;

interface AdminStatsProviderInterface
{
    /** @return array{totalUsers: int, activeUsers: int, inactiveUsers: int, mrr: float, arr: float} */
    public function getStats(): array;
}
