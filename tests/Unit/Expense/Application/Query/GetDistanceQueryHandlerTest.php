<?php

declare(strict_types=1);

use App\Expense\Application\Query\GetDistance\GetDistanceQuery;
use App\Expense\Application\Query\GetDistance\GetDistanceQueryHandler;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

it('retourne la distance en km arrondie à 0.1', function () {
    $geojson = json_encode([
        'features' => [[
            'properties' => ['summary' => ['distance' => 12345.6]],
        ]],
    ]);

    $httpClient = new MockHttpClient([
        new MockResponse($geojson, ['http_code' => 200]),
    ]);

    $handler = new GetDistanceQueryHandler($httpClient);
    $result = $handler(new GetDistanceQuery(48.8566, 2.3522, 48.9021, 2.3611));

    expect($result)->toBe(12.3);
});

it('lève une RuntimeException si ORS est indisponible', function () {
    $httpClient = new MockHttpClient([
        new MockResponse('', ['http_code' => 503]),
    ]);

    $handler = new GetDistanceQueryHandler($httpClient);

    expect(fn () => $handler(new GetDistanceQuery(48.8566, 2.3522, 48.9021, 2.3611)))
        ->toThrow(\RuntimeException::class);
});

it('lève une RuntimeException si la réponse ne contient aucun itinéraire', function () {
    $geojson = json_encode(['features' => []]);

    $httpClient = new MockHttpClient([
        new MockResponse($geojson, ['http_code' => 200]),
    ]);

    $handler = new GetDistanceQueryHandler($httpClient);

    expect(fn () => $handler(new GetDistanceQuery(48.8566, 2.3522, 48.9021, 2.3611)))
        ->toThrow(\RuntimeException::class);
});