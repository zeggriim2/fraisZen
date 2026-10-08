<?php

declare(strict_types=1);

use App\AgentLease\Application\IssueLeaseService;
use App\AgentLease\Domain\Repository\IssueLeaseRepositoryInterface;

final class InMemoryIssueLeaseRepository implements IssueLeaseRepositoryInterface
{
    /** @var array<string, array{hash: string, owner: string, expires: DateTimeImmutable}> */
    public array $leases = [];

    public function acquire(string $repositoryFullName, int $issueNumber, string $tokenHash, string $ownerId, DateTimeImmutable $expiresAt, DateTimeImmutable $now): bool
    {
        $key = $repositoryFullName.'#'.$issueNumber;
        if (isset($this->leases[$key]) && $this->leases[$key]['expires'] > $now) {
            return false;
        }
        $this->leases[$key] = ['hash' => $tokenHash, 'owner' => $ownerId, 'expires' => $expiresAt];
        return true;
    }

    public function renew(string $repositoryFullName, int $issueNumber, string $ownerId, string $tokenHash, DateTimeImmutable $expiresAt, DateTimeImmutable $now): bool
    {
        $key = $repositoryFullName.'#'.$issueNumber;
        if (!isset($this->leases[$key]) || $this->leases[$key]['owner'] !== $ownerId || $this->leases[$key]['hash'] !== $tokenHash || $this->leases[$key]['expires'] <= $now) {
            return false;
        }
        $this->leases[$key]['expires'] = $expiresAt;
        return true;
    }

    public function release(string $repositoryFullName, int $issueNumber, string $ownerId, string $tokenHash, DateTimeImmutable $now): bool
    {
        $key = $repositoryFullName.'#'.$issueNumber;
        if (!isset($this->leases[$key]) || $this->leases[$key]['owner'] !== $ownerId || $this->leases[$key]['hash'] !== $tokenHash || $this->leases[$key]['expires'] <= $now) {
            return false;
        }
        unset($this->leases[$key]);
        return true;
    }
}

it('allows one owner at a time and does not expose the stored hash', function () {
    $repository = new InMemoryIssueLeaseRepository();
    $service = new IssueLeaseService($repository, 60);
    $now = new DateTimeImmutable('2026-01-01 12:00:00');

    $first = $service->acquire('zeggriim2/fraisZen', 42, 'worker-a', $now);
    $second = $service->acquire('zeggriim2/fraisZen', 42, 'worker-b', $now);

    expect($first)->not->toBeNull()
        ->and($first['token'])->toHaveLength(43)
        ->and($repository->leases['zeggriim2/fraisZen#42']['hash'])->not->toBe($first['token'])
        ->and($second)->toBeNull();
});

it('renews and releases only with the opaque token', function () {
    $repository = new InMemoryIssueLeaseRepository();
    $service = new IssueLeaseService($repository, 60);
    $now = new DateTimeImmutable('2026-01-01 12:00:00');
    $lease = $service->acquire('org/repo', 7, 'worker-a', $now);

    expect($service->renew('org/repo', 7, 'worker-a', 'wrong-token', $now))->toBeNull()
        ->and($service->renew('org/repo', 7, 'worker-a', $lease['token'], $now->modify('+30 seconds')))->toEqual($now->modify('+90 seconds'))
        ->and($service->release('org/repo', 7, 'worker-a', 'wrong-token', $now))->toBeFalse()
        ->and($service->release('org/repo', 7, 'worker-a', $lease['token'], $now))->toBeTrue()
        ->and($repository->leases)->toBeEmpty();
});

it('allows acquisition after expiration', function () {
    $repository = new InMemoryIssueLeaseRepository();
    $service = new IssueLeaseService($repository, 60);
    $first = $service->acquire('org/repo', 7, 'worker-a', new DateTimeImmutable('2026-01-01 12:00:00'));
    $second = $service->acquire('org/repo', 7, 'worker-b', new DateTimeImmutable('2026-01-01 12:01:01'));

    expect($first)->not->toBeNull()->and($second)->not->toBeNull()->and($second['token'])->not->toBe($first['token']);
});
