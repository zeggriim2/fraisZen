<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\PublicHoliday;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/** @psalm-api */
final class PublicHolidayService
{
    private const TTL = 86400;

    public function __construct(
        private readonly PublicHolidayApiClient $apiClient,
        private readonly CacheInterface $cache,
    ) {
    }

    /** @return array<string, string> */
    public function forYear(int $year): array
    {
        return $this->cache->get("public_holidays_{$year}", function (ItemInterface $item) use ($year): array {
            $item->expiresAfter(self::TTL);

            return $this->apiClient->forYear($year);
        });
    }
}
