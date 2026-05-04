<?php

declare(strict_types=1);

namespace App\Expense\Application\Query\GetDistance;

use App\SharedKernel\Application\Bus\QueryHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetDistanceQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    public function __invoke(GetDistanceQuery $query): float
    {
        $response = $this->httpClient->request('GET', '/v2/directions/driving-car', [
            'query' => [
                'start' => "{$query->fromLng},{$query->fromLat}",
                'end' => "{$query->toLng},{$query->toLat}",
            ],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException('ORS unavailable: HTTP '.$response->getStatusCode());
        }

        $data = $response->toArray();

        if (empty($data['features'])) {
            throw new \RuntimeException('ORS returned no route.');
        }

        $meters = (float) $data['features'][0]['properties']['summary']['distance'];

        return round($meters / 1000.0, 1);
    }
}
