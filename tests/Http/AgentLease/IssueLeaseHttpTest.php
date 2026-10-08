<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Component\HttpClient\HttpClient;

function leaseHttpClient(): HttpClientInterface
{
    return HttpClient::create([
        'base_uri' => 'https://localhost',
        'verify_peer' => false,
        'verify_host' => false,
    ]);
}

function leaseHttpDatabase(): Connection
{
    $url = getenv('DATABASE_URL');

    if (false === $url || '' === $url) {
        throw new RuntimeException('DATABASE_URL is required; run make test-agent-lease-http.');
    }

    $parts = parse_url($url);
    if (!is_array($parts) || !isset($parts['host'], $parts['user'], $parts['pass'], $parts['path'])) {
        throw new RuntimeException('DATABASE_URL must be a valid MySQL DSN.');
    }

    return DriverManager::getConnection([
        'driver' => 'pdo_mysql',
        'host' => $parts['host'],
        'port' => $parts['port'] ?? 3306,
        'user' => $parts['user'],
        'password' => $parts['pass'],
        'dbname' => ltrim($parts['path'], '/'),
    ]);
}

/** @param array<string, mixed> $json */
function leaseHttpRequest(HttpClientInterface $client, string $method, string $uri, array $json = [], ?string $token = null): ResponseInterface
{
    $options = ['json' => $json];
    if (null !== $token) {
        $options['auth_bearer'] = $token;
    }

    return $client->request($method, $uri, $options);
}

/** @return array{id: string, token: string} */
function createActiveLeaseUser(HttpClientInterface $client, Connection $database): array
{
    $email = 'lease-http-'.bin2hex(random_bytes(8)).'@example.com';
    $password = 'Test1234!';
    $registered = leaseHttpRequest($client, 'POST', '/api/auth/register', compact('email', 'password'));

    expect($registered->getStatusCode())->toBe(201);
    $id = (string) ($registered->toArray(false)['id'] ?? '');
    expect($id)->not->toBeEmpty();

    $database->executeStatement('UPDATE `user` SET subscription_status = ? WHERE id = ?', ['active', $id]);
    $login = leaseHttpRequest($client, 'POST', '/api/auth/login', compact('email', 'password'));

    expect($login->getStatusCode())->toBe(200);
    $token = (string) ($login->toArray(false)['token'] ?? '');
    expect($token)->not->toBeEmpty();

    return ['id' => $id, 'token' => $token];
}

it('protects the lease lifecycle over HTTP', function (): void {
    $client = leaseHttpClient();
    $database = leaseHttpDatabase();
    $users = [];

    $database->executeStatement('DELETE FROM issue_lease WHERE repository_full_name = ? AND issue_number = ?', ['org/http-test', 501]);

    try {
        $unauthenticated = leaseHttpRequest($client, 'POST', '/api/agent/leases', [
            'repository' => 'org/http-test',
            'issue' => 501,
        ]);
        expect($unauthenticated->getStatusCode())->toBe(401);

        $missingRoute = leaseHttpRequest($client, 'GET', '/api/agent/lease-does-not-exist');
        expect($missingRoute->getStatusCode())->toBe(404);

        $users[] = createActiveLeaseUser($client, $database);
        $users[] = createActiveLeaseUser($client, $database);
        [$owner, $other] = $users;
        $leaseData = ['repository' => 'org/http-test', 'issue' => 501];

        $acquired = leaseHttpRequest($client, 'POST', '/api/agent/leases', $leaseData, $owner['token']);
        expect($acquired->getStatusCode())->toBe(201);
        $token = (string) ($acquired->toArray(false)['token'] ?? '');
        expect($token)->not->toBeEmpty();

        $conflict = leaseHttpRequest($client, 'POST', '/api/agent/leases', $leaseData, $owner['token']);
        expect($conflict->getStatusCode())->toBe(409);

        $otherRenew = leaseHttpRequest($client, 'PATCH', '/api/agent/leases', $leaseData + ['token' => $token], $other['token']);
        $otherRelease = leaseHttpRequest($client, 'DELETE', '/api/agent/leases', $leaseData + ['token' => $token], $other['token']);
        expect($otherRenew->getStatusCode())->toBe(409)->and($otherRelease->getStatusCode())->toBe(409);

        $wrongRenew = leaseHttpRequest($client, 'PATCH', '/api/agent/leases', $leaseData + ['token' => 'wrong-token'], $owner['token']);
        $wrongRelease = leaseHttpRequest($client, 'DELETE', '/api/agent/leases', $leaseData + ['token' => 'wrong-token'], $owner['token']);
        expect($wrongRenew->getStatusCode())->toBe(409)->and($wrongRelease->getStatusCode())->toBe(409);

        $renewed = leaseHttpRequest($client, 'PATCH', '/api/agent/leases', $leaseData + ['token' => $token], $owner['token']);
        expect($renewed->getStatusCode())->toBe(200)
            ->and($renewed->toArray(false)['expiresAt'] ?? null)->toBeString();

        $released = leaseHttpRequest($client, 'DELETE', '/api/agent/leases', $leaseData + ['token' => $token], $owner['token']);
        expect($released->getStatusCode())->toBe(204);
    } finally {
        $database->executeStatement('DELETE FROM issue_lease WHERE repository_full_name = ? AND issue_number = ?', ['org/http-test', 501]);
        foreach ($users as $user) {
            $database->executeStatement('DELETE FROM `user` WHERE id = ?', [$user['id']]);
        }
        $database->close();
    }
});