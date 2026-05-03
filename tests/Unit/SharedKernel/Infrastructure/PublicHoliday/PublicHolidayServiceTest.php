<?php

declare(strict_types=1);

use App\SharedKernel\Infrastructure\PublicHoliday\PublicHolidayService;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

it('retourne les jours fériés depuis lAPI datagouv', function () {
    $payload = json_encode([
        '2025-01-01' => '1er janvier',
        '2025-05-01' => 'Fête du Travail',
    ]);

    $httpClient = new MockHttpClient([
        new MockResponse($payload, ['http_code' => 200]),
    ]);

    $cache = new class implements CacheInterface {
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

    $service = new PublicHolidayService($httpClient, $cache);
    $result = $service->forYear(2025);

    expect($result)->toBe([
        '2025-01-01' => '1er janvier',
        '2025-05-01' => 'Fête du Travail',
    ]);
});

it('retourne un tableau vide si lAPI est indisponible', function () {
    $httpClient = new MockHttpClient([
        new MockResponse('', ['http_code' => 503]),
    ]);

    $cache = new class implements CacheInterface {
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

    $service = new PublicHolidayService($httpClient, $cache);
    $result = $service->forYear(2025);

    expect($result)->toBe([]);
});