<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\PublicHoliday;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PublicHolidayService
{
    private const TTL = 86400;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
    ) {}

    /** @return array<string, string> */
    public function forYear(int $year): array
    {
        return $this->cache->get("public_holidays_{$year}", function (ItemInterface $item) use ($year): array {
            $item->expiresAfter(self::TTL);
            try {
                $response = $this->httpClient->request('GET', "/jours-feries/metropole/{$year}.json");
                if (200 !== $response->getStatusCode()) {
                    return [];
                }
                return $response->toArray();
            } catch (\Throwable) {
                return [];
            }
        });
    }
}
