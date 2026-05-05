<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\PublicHoliday;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class PublicHolidayApiClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    /** @return array<string, string> */
    public function forYear(int $year): array
    {
        try {
            $response = $this->httpClient->request(Request::METHOD_GET, "/jours-feries/metropole/{$year}.json");
            if (Response::HTTP_OK !== $response->getStatusCode()) {
                return [];
            }

            return $response->toArray();
        } catch (\Throwable) {
            return [];
        }
    }
}
