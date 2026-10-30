<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Infrastructure;

use App\Tax\Domain\Entity\TaxYearClosure;
use App\Tax\Domain\Exception\TaxYearClosureConflictException;
use App\Tax\Domain\Service\TaxComparisonResult;
use App\Tax\Infrastructure\Persistence\DoctrineTaxYearClosureRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

/**
 * Persistence of closures: real Doctrine attribute mapping on an in-memory SQLite database,
 * snapshot round trip, unique version per person and year, optimistic lock on reopening.
 */
final class TaxYearClosurePersistenceTest extends TestCase
{
    private Configuration $config;
    private Connection $connection;
    private EntityManager $em;

    protected function setUp(): void
    {
        $root = \dirname(__DIR__, 4);
        $this->config = ORMSetup::createAttributeMetadataConfig([$root.'/src/Tax/Domain/Entity'], true);
        $this->config->setNamingStrategy(new UnderscoreNamingStrategy(\CASE_LOWER, true));
        $this->config->enableNativeLazyObjects(true);
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $this->config);
        $this->em = new EntityManager($this->connection, $this->config);
        (new SchemaTool($this->em))->createSchema([$this->em->getClassMetadata(TaxYearClosure::class)]);
    }

    public function testTheSnapshotRoundTripsAndRebuildsTheClosedResult(): void
    {
        $repository = new DoctrineTaxYearClosureRepository($this->em);
        $closure = $this->closure('c1', 'person-a', 2026, 1, 100_000);
        $snapshot = $closure->snapshot();
        $repository->add($closure);
        $this->em->clear();

        $found = $repository->findActive('person-a', 2026);

        self::assertNotNull($found);
        self::assertNotSame($closure, $found);
        self::assertSame('c1', $found->id());
        self::assertSame(1, $found->version());
        self::assertSame('user-1', $found->closedBy());
        self::assertSame('2027-02-10 09:30:00', $found->closedAt()->format('Y-m-d H:i:s'));
        self::assertSame(str_repeat('a', 64), $found->fingerprint());
        self::assertEquals($snapshot, $found->snapshot());
        self::assertSame($snapshot['calculation'], $found->closedResult()->toArray());
        self::assertSame(['code' => 'forfait_rate_percent', 'value' => 10, 'version' => '2026.1', 'source' => 'BOFiP', 'effectiveFrom' => '2026-01-01'], $found->snapshot()['fiscalRules']['rules'][0] ?? null);
        self::assertSame(['netTaxableSalaries' => [['id' => 's-a', 'amountCents' => 2_000_000]]], $found->snapshot()['retainedData']);
        self::assertTrue($found->isClosed());
        self::assertNull($found->reopenedAt());
    }

    public function testHistoryIsOrderedAndIsolatedPerPersonAndYearAndReopeningKeepsIt(): void
    {
        $repository = new DoctrineTaxYearClosureRepository($this->em);
        $repository->add($this->closure('c1', 'person-a', 2026, 1, 100_000));
        $repository->add($this->closure('b1', 'person-b', 2026, 1, 7));
        $repository->add($this->closure('a25', 'person-a', 2025, 1, 8));
        $first = $repository->findActive('person-a', 2026);
        self::assertNotNull($first);
        $first->reopen('user-2', new \DateTimeImmutable('2027-03-01 08:00:00'));
        $repository->save($first);
        $repository->add($this->closure('c2', 'person-a', 2026, 2, 50_000));
        $this->em->clear();

        $history = $repository->findHistory('person-a', 2026);

        self::assertSame(['c1', 'c2'], array_map(static fn (TaxYearClosure $c): string => $c->id(), $history));
        self::assertFalse($history[0]->isClosed());
        self::assertSame('user-2', $history[0]->reopenedBy());
        self::assertSame('2027-03-01 08:00:00', $history[0]->reopenedAt()?->format('Y-m-d H:i:s'));
        self::assertSame(2, $history[0]->revision());
        self::assertSame(100_000, $history[0]->closedResult()->amounts()['differenceCents'] ?? null, 'the reopened snapshot is kept');
        self::assertSame('c2', $repository->findActive('person-a', 2026)?->id());
        self::assertSame('b1', $repository->findActive('person-b', 2026)?->id());
        self::assertSame(['a25'], array_map(static fn (TaxYearClosure $c): string => $c->id(), $repository->findHistory('person-a', 2025)));
        self::assertNull($repository->findActive('person-c', 2026));
    }

    public function testTwoConcurrentClosingsOfTheSameVersionCannotBothBeStored(): void
    {
        $first = new DoctrineTaxYearClosureRepository($this->em);
        $secondEm = new EntityManager($this->connection, $this->config);
        $second = new DoctrineTaxYearClosureRepository($secondEm);

        $first->add($this->closure('c1', 'person-a', 2026, 1, 100_000));

        try {
            $second->add($this->closure('c1-concurrent', 'person-a', 2026, 1, 999));
            self::fail('the same version must not be stored twice');
        } catch (TaxYearClosureConflictException $e) {
            self::assertStringContainsString('already closed (version 1)', $e->getMessage());
        }

        $this->em->clear();
        $history = $first->findHistory('person-a', 2026);
        self::assertSame(['c1'], array_map(static fn (TaxYearClosure $c): string => $c->id(), $history));
        self::assertSame(100_000, $history[0]->closedResult()->amounts()['differenceCents'] ?? null);
    }

    public function testTwoConcurrentReopeningsAreDetectedByTheOptimisticLock(): void
    {
        (new DoctrineTaxYearClosureRepository($this->em))->add($this->closure('c1', 'person-a', 2026, 1, 100_000));
        $this->em->clear();

        $emA = new EntityManager($this->connection, $this->config);
        $emB = new EntityManager($this->connection, $this->config);
        $repoA = new DoctrineTaxYearClosureRepository($emA);
        $repoB = new DoctrineTaxYearClosureRepository($emB);
        $seenByA = $repoA->findActive('person-a', 2026);
        $seenByB = $repoB->findActive('person-a', 2026);
        self::assertNotNull($seenByA);
        self::assertNotNull($seenByB);

        $seenByA->reopen('user-a', new \DateTimeImmutable('2027-03-01 08:00:00'));
        $repoA->save($seenByA);

        $seenByB->reopen('user-b', new \DateTimeImmutable('2027-03-01 08:00:01'));
        try {
            $repoB->save($seenByB);
            self::fail('the stale reopening must be rejected');
        } catch (TaxYearClosureConflictException $e) {
            self::assertStringContainsString('modified by another request', $e->getMessage());
        }

        $this->em->clear();
        $stored = (new DoctrineTaxYearClosureRepository($this->em))->findHistory('person-a', 2026)[0];
        self::assertSame('user-a', $stored->reopenedBy());
    }

    private function closure(string $id, string $personId, int $year, int $version, int $difference): TaxYearClosure
    {
        $amounts = [
            'netTaxableSalaryCents' => 2_000_000, 'forfaitRatePercent' => 10, 'theoreticalForfaitCents' => 200_000,
            'forfaitMinimumCents' => 50_000, 'forfaitMaximumCents' => 1_400_000, 'forfaitLimitApplied' => 'none',
            'forfaitCents' => 200_000, 'realExpensesCents' => 200_000 + $difference, 'reintegratedReimbursementsCents' => 0,
            'excludedAlreadyDeductedContributionCents' => 0, 'baseForfaitCents' => 1_800_000,
            'baseRealExpensesCents' => 1_800_000 - $difference, 'differenceCents' => $difference,
        ];
        $result = TaxComparisonResult::available(
            $personId,
            $year,
            $amounts,
            [['code' => 'forfait_rate_percent', 'value' => 10, 'version' => $year.'.1', 'source' => 'BOFiP', 'effectiveFrom' => $year.'-01-01']],
            ['year' => $year, 'version' => $year.'.1', 'source' => 'BOFiP', 'effectiveFrom' => $year.'-01-01'],
            [],
        );
        $origins = ['netTaxableSalary' => ['entries' => 1, 'employers' => ['ACME']], 'realExpenses' => ['entries' => 1, 'trips' => 0, 'travelAllowanceCents' => null], 'reimbursements' => ['entries' => 0, 'reintegrated' => 0]];

        return TaxYearClosure::close($id, $personId, $year, $version, 'user-1', new \DateTimeImmutable('2027-02-10 09:30:00'), $result, $origins, ['netTaxableSalaries' => [['id' => 's-a', 'amountCents' => 2_000_000]]], str_repeat('a', 64));
    }
}
