<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\PublicHoliday;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service for retrieving French public holidays from calendrier.api.gouv.fr.
 * Implementation will be completed in Task 2.
 */
final class PublicHolidayService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }
}