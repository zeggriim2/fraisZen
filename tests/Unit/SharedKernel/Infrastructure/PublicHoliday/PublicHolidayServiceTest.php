<?php

declare(strict_types=1);

use App\SharedKernel\Infrastructure\PublicHoliday\PublicHolidayApiClient;
use App\SharedKernel\Infrastructure\PublicHoliday\PublicHolidayService;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

function makeCacheHit(mixed $cached): CacheInterface
{
    return new class($cached) implements CacheInterface {
        public function __construct(private readonly mixed $cached) {}

        public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
        {
            return $this->cached;
        }

        public function delete(string $key): bool { return true; }
    };
}

function makeCacheMiss(): CacheInterface
{
    return new class implements CacheInterface {
        public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
        {
            $item = new class implements ItemInterface {
                public function getKey(): string { return ''; }
                public function get(): mixed { return null; }
                public function isHit(): bool { return false; }
                public function set(mixed $value): static { return $this; }
                public function expiresAt(?\DateTimeInterface $expiration): static { return $this; }
                public function expiresAfter(int|\DateInterval|null $time): static { return $this; }
                public function tag(string|iterable $tags): static { return $this; }
                public function getMetadata(): array { return []; }
            };

            return $callback($item);
        }

        public function delete(string $key): bool { return true; }
    };
}

it('retourne les données du cache sans appeler lAPI', function () {
    $cached = ['2025-01-01' => '1er janvier'];

    $service = new PublicHolidayService(
        new PublicHolidayApiClient(new MockHttpClient()),
        makeCacheHit($cached),
    );

    expect($service->forYear(2025))->toBe($cached);
});

it('délègue à lAPI client en cas de cache miss', function () {
    $payload = json_encode(['2025-05-01' => 'Fête du Travail']);

    $service = new PublicHolidayService(
        new PublicHolidayApiClient(new MockHttpClient([new MockResponse($payload, ['http_code' => 200])])),
        makeCacheMiss(),
    );

    expect($service->forYear(2025))->toBe(['2025-05-01' => 'Fête du Travail']);
});