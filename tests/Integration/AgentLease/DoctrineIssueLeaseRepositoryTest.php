<?php

declare(strict_types=1);

use App\AgentLease\Infrastructure\Persistence\DoctrineIssueLeaseRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

function leaseTestConnection(): Connection
{
    $url = getenv('DATABASE_URL');

    if (false === $url || '' === $url) {
        throw new RuntimeException('DATABASE_URL is required; run make test-agent-lease.');
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
        'dbname' => ltrim($parts['path'], '/').'_test',
    ]);
}

beforeEach(function (): void {
    $this->connection = leaseTestConnection();
    $this->connection->executeStatement('DELETE FROM issue_lease');
});

afterEach(function (): void {
    $this->connection->executeStatement('DELETE FROM issue_lease');
    $this->connection->close();
});

it('uses an InnoDB table created by the migration', function (): void {
    expect($this->connection->fetchOne("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'issue_lease'"))->toBe('InnoDB');
});

it('allows only one concurrent acquisition for an issue', function (): void {
    if (!function_exists('pcntl_fork')) {
        throw new RuntimeException('pcntl is required to prove concurrent acquisition.');
    }

    $this->connection->close();
    $directory = sys_get_temp_dir().'/agent-lease-'.bin2hex(random_bytes(4));
    mkdir($directory);
    $start = $directory.'/start';
    $ready = [$directory.'/ready-a', $directory.'/ready-b'];
    $results = [$directory.'/result-a', $directory.'/result-b'];
    $children = [];

    foreach ([0, 1] as $index) {
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new RuntimeException('Could not fork integration test worker.');
        }
        if (0 === $pid) {
            $connection = leaseTestConnection();
            $repository = new DoctrineIssueLeaseRepository($connection);
            file_put_contents($ready[$index], 'ready');
            while (!is_file($start)) {
                usleep(1000);
            }
            $acquired = $repository->acquire('org/repo', 99, hash('sha256', 'token-'.$index), 'worker-'.$index, new DateTimeImmutable('+60 seconds'), new DateTimeImmutable());
            file_put_contents($results[$index], $acquired ? '1' : '0');
            exit(0);
        }
        $children[] = $pid;
    }

    $deadline = microtime(true) + 5;
    while ((!is_file($ready[0]) || !is_file($ready[1])) && microtime(true) < $deadline) {
        usleep(10000);
    }
    expect(is_file($ready[0]) && is_file($ready[1]))->toBeTrue();
    file_put_contents($start, 'go');

    foreach ($children as $pid) {
        pcntl_waitpid($pid, $status);
    }

    expect((int) file_get_contents($results[0]) + (int) file_get_contents($results[1]))->toBe(1);

    foreach (array_merge($ready, $results, [$start]) as $file) {
        @unlink($file);
    }
    @rmdir($directory);
});

it('supports expiration, renewal and token-checked release in MySQL', function (): void {
    $repository = new DoctrineIssueLeaseRepository($this->connection);
    $createdAt = new DateTimeImmutable('2026-01-01 12:00:00');
    $expiresAt = new DateTimeImmutable('2026-01-01 12:01:00');
    $hash = hash('sha256', 'token-a');

    expect($repository->acquire('org/repo', 7, $hash, 'worker-a', $expiresAt, $createdAt))->toBeTrue()
        ->and($repository->renew('org/repo', 7, 'worker-a', hash('sha256', 'wrong'), new DateTimeImmutable('2026-01-01 12:02:00'), $createdAt))->toBeFalse()
        ->and($repository->renew('org/repo', 7, 'worker-a', $hash, new DateTimeImmutable('2026-01-01 12:02:00'), new DateTimeImmutable('2026-01-01 12:00:30')))->toBeTrue()
        ->and($repository->release('org/repo', 7, 'worker-a', hash('sha256', 'wrong'), new DateTimeImmutable('2026-01-01 12:00:31')))->toBeFalse()
        ->and($repository->release('org/repo', 7, 'worker-a', $hash, new DateTimeImmutable('2026-01-01 12:00:31')))->toBeTrue();

    expect($repository->acquire('org/repo', 7, hash('sha256', 'token-b'), 'worker-b', new DateTimeImmutable('2026-01-01 12:04:00'), new DateTimeImmutable('2026-01-01 12:03:00')))->toBeTrue();
});
