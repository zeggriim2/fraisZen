<?php

declare(strict_types=1);

use App\SharedKernel\Infrastructure\PublicHoliday\PublicHolidayApiClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

it('retourne les jours fériés depuis lAPI datagouv', function () {
    $payload = json_encode([
        '2025-01-01' => '1er janvier',
        '2025-05-01' => 'Fête du Travail',
    ]);

    $client = new PublicHolidayApiClient(
        new MockHttpClient([new MockResponse($payload, ['http_code' => 200])]),
    );

    expect($client->forYear(2025))->toBe([
        '2025-01-01' => '1er janvier',
        '2025-05-01' => 'Fête du Travail',
    ]);
});

it('retourne un tableau vide si lAPI est indisponible', function () {
    $client = new PublicHolidayApiClient(
        new MockHttpClient([new MockResponse('', ['http_code' => 503])]),
    );

    expect($client->forYear(2025))->toBe([]);
});