<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Application;

use App\Admin\Domain\Entity\FiscalConfig;
use App\Expense\Domain\Entity\ParkingExpense;
use App\Expense\Domain\Repository\BaremeKilometriqueRepositoryInterface;
use App\Expense\Domain\Service\KilometricAllowanceCalculator;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\Tax\Application\Command\CloseTaxYear\CloseTaxYearCommand;
use App\Tax\Application\Command\CloseTaxYear\CloseTaxYearCommandHandler;
use App\Tax\Application\Command\ReopenTaxYear\ReopenTaxYearCommand;
use App\Tax\Application\Command\ReopenTaxYear\ReopenTaxYearCommandHandler;
use App\Tax\Application\Presenter\TaxComparisonPresenter;
use App\Tax\Application\Query\GetTaxComparison\GetTaxComparisonQuery;
use App\Tax\Application\Query\GetTaxComparison\GetTaxComparisonQueryHandler;
use App\Tax\Application\Service\TaxComparisonCalculator;
use App\Tax\Domain\Entity\EmployerReimbursement;
use App\Tax\Domain\Entity\NetTaxableSalary;
use App\Tax\Domain\Entity\TaxYearClosure;
use App\Tax\Domain\Exception\TaxYearClosureConflictException;
use App\Tax\Domain\Exception\TaxYearNotClosableException;
use App\Tax\Domain\Repository\TaxYearClosureRepositoryInterface;
use App\Tax\Domain\Service\TaxComparisonResult;
use App\Tests\Unit\Tax\Support\InMemoryEmployerReimbursements;
use App\Tests\Unit\Tax\Support\InMemoryExpenses;
use App\Tests\Unit\Tax\Support\InMemoryFiscalConfigs;
use App\Tests\Unit\Tax\Support\InMemoryNetTaxableSalaries;
use App\Tests\Unit\Tax\Support\InMemoryTaxYearClosures;
use PHPUnit\Framework\TestCase;

/**
 * Validation and closing of a year, immutable snapshot, invalidation before closing, protection
 * after closing, explicit reopening with history, concurrency and isolation per person.
 */
final class TaxYearClosureTest extends TestCase
{
    private const A = 'person-a';
    private const B = 'person-b';
    private const USER = 'user-1';
    private const TODAY = '2027-02-10';

    private InMemoryNetTaxableSalaries $salaries;
    private InMemoryEmployerReimbursements $reimbursements;
    private InMemoryExpenses $expenses;
    private InMemoryFiscalConfigs $configs;
    private InMemoryTaxYearClosures $closures;
    private TaxComparisonCalculator $calculator;
    private GetTaxComparisonQueryHandler $query;
    private CloseTaxYearCommandHandler $close;
    private ReopenTaxYearCommandHandler $reopen;

    protected function setUp(): void
    {
        $this->salaries = new InMemoryNetTaxableSalaries();
        $this->reimbursements = new InMemoryEmployerReimbursements();
        $this->expenses = new InMemoryExpenses();
        $this->configs = new InMemoryFiscalConfigs();
        $this->closures = new InMemoryTaxYearClosures();
        $kilometric = new KilometricAllowanceCalculator($this->createStub(BaremeKilometriqueRepositoryInterface::class));

        $this->calculator = new TaxComparisonCalculator($this->salaries, $this->reimbursements, $this->expenses, $this->configs, $kilometric);
        $this->query = new GetTaxComparisonQueryHandler($this->salaries, $this->reimbursements, $this->expenses, $this->configs, $kilometric, closures: $this->closures);
        $this->close = new CloseTaxYearCommandHandler($this->calculator, $this->closures);
        $this->reopen = new ReopenTaxYearCommandHandler($this->closures);

        $this->configs->save(new FiscalConfig(2026, '2.70', '5.35', 10, 50_000, 1_400_000, '2026.1', 'BOFiP-IR-BASE-2026', new \DateTimeImmutable('2026-01-01')));
        // Person A: forfait 200 000, real expenses 300 000 => difference 100 000 cents.
        $this->salaries->save(new NetTaxableSalary('s-a', self::A, 'ACME', 2026, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'), 2_000_000));
        $this->expenses->save(new ParkingExpense(ExpenseId::fromString('aaaaaaaa-0000-4000-8000-000000000001'), self::A, new \DateTimeImmutable('2026-03-01'), null, 3_000.00));
        // Person B of the same household: forfait 300 000, real expenses 100 000 => difference -200 000 cents.
        $this->salaries->save(new NetTaxableSalary('s-b', self::B, 'Beta', 2026, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'), 3_000_000));
        $this->expenses->save(new ParkingExpense(ExpenseId::fromString('bbbbbbbb-0000-4000-8000-000000000001'), self::B, new \DateTimeImmutable('2026-03-01'), null, 1_000.00));
    }

    public function testAnOpenAvailableYearIsClosableWithTheFingerprintOfTheReviewedCalculation(): void
    {
        $open = $this->read(self::A);

        self::assertSame('available', $open['status']);
        self::assertSame('annual_estimate_to_verify', $open['display']['temporalQualification']);
        self::assertSame('open', $open['closure']['state']);
        self::assertTrue($open['closure']['closable']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $open['closure']['calculationFingerprint']);
        self::assertSame($this->calculator->compute(self::A, 2026)->fingerprint, $open['closure']['calculationFingerprint']);
        self::assertNull($open['closure']['version']);
        self::assertNull($open['closure']['afterClosure']);
        self::assertSame([], $open['closure']['history']);
    }

    public function testClosingPersistsAnImmutableSnapshotOfCalculationDataRulesAndDate(): void
    {
        $summary = $this->closeYear(self::A, '2027-02-10T09:30:00+00:00');

        self::assertSame(['personId' => self::A, 'year' => 2026, 'version' => 1, 'state' => 'closed', 'closedAt' => '2027-02-10T09:30:00+00:00', 'reopenedAt' => null], $summary);
        self::assertCount(1, $this->closures->rows);
        $closure = $this->closures->rows[0];
        self::assertSame(self::USER, $closure->closedBy());
        $snapshot = $closure->snapshot();

        self::assertSame(['schemaVersion', 'closedAt', 'fingerprint', 'calculation', 'origins', 'retainedData', 'fiscalRules'], array_keys($snapshot));
        self::assertSame(1, $snapshot['schemaVersion']);
        self::assertSame('2027-02-10T09:30:00+00:00', $snapshot['closedAt']);
        self::assertSame($closure->fingerprint(), $snapshot['fingerprint']);
        // Calculation: every cent of the closed result.
        self::assertSame('available', $snapshot['calculation']['status']);
        self::assertSame(self::A, $snapshot['calculation']['personId']);
        self::assertIsArray($snapshot['calculation']['amounts']);
        self::assertSame(2_000_000, $snapshot['calculation']['amounts']['netTaxableSalaryCents']);
        self::assertSame(200_000, $snapshot['calculation']['amounts']['forfaitCents']);
        self::assertSame(300_000, $snapshot['calculation']['amounts']['realExpensesCents']);
        self::assertSame(100_000, $snapshot['calculation']['amounts']['differenceCents']);
        // Retained data: only person A's inputs, with the fiscal configuration used.
        $retained = $snapshot['retainedData'];
        self::assertSame([['id' => 's-a', 'employer' => 'ACME', 'periodStart' => '2026-01-01', 'periodEnd' => '2026-12-31', 'amountCents' => 2_000_000]], $retained['netTaxableSalaries']);
        self::assertSame([['id' => 'aaaaaaaa-0000-4000-8000-000000000001', 'type' => 'parking', 'date' => '2026-03-01', 'amountCents' => 300_000]], $retained['expenses']);
        self::assertSame([], $retained['employerReimbursements']);
        self::assertNull($retained['travelAllowanceCents']);
        self::assertSame(['year' => 2026, 'forfaitRatePercent' => 10, 'forfaitMinimumCents' => 50_000, 'forfaitMaximumCents' => 1_400_000, 'version' => '2026.1', 'source' => 'BOFiP-IR-BASE-2026', 'effectiveFrom' => '2026-01-01'], $retained['fiscalConfiguration']);
        // Fiscal rules with their version, source and effective date.
        self::assertSame(['year' => 2026, 'version' => '2026.1', 'source' => 'BOFiP-IR-BASE-2026', 'effectiveFrom' => '2026-01-01'], $snapshot['fiscalRules']['configuration']);
        self::assertIsArray($snapshot['fiscalRules']['rules']);
        self::assertContains(['code' => 'forfait_rate_percent', 'value' => 10, 'version' => '2026.1', 'source' => 'BOFiP-IR-BASE-2026', 'effectiveFrom' => '2026-01-01'], $snapshot['fiscalRules']['rules']);
        self::assertSame($snapshot['calculation']['rules'], $snapshot['fiscalRules']['rules']);
    }

    public function testAClosedYearIsReadFromItsSnapshotWithTheClosedStateAndNoFinalTaxWording(): void
    {
        $before = $this->read(self::A);
        $this->closeYear(self::A, '2027-02-10T09:30:00+00:00');

        $closed = $this->read(self::A);

        self::assertSame('closed', $closed['status']);
        self::assertSame('closed_estimate', $closed['display']['temporalQualification']);
        self::assertSame(TaxComparisonPresenter::DISCLAIMER_KEY, $closed['display']['disclaimerKey']);
        self::assertSame($before['amounts'], $closed['amounts']);
        self::assertSame($before['rules'], $closed['rules']);
        self::assertSame($before['configuration'], $closed['configuration']);
        self::assertSame($before['origins'], $closed['origins']);
        self::assertSame('advantageous', $closed['decision']);
        self::assertTrue($closed['display']['showBanner']);
        self::assertSame(1_000, $closed['display']['roundedDifferenceEuros']);
        self::assertSame('closed', $closed['closure']['state']);
        self::assertFalse($closed['closure']['closable']);
        self::assertSame(1, $closed['closure']['version']);
        self::assertSame('2027-02-10T09:30:00+00:00', $closed['closure']['closedAt']);
        self::assertSame(['policy' => 'snapshot_kept_reopen_to_recalculate', 'dataChanged' => false, 'currentStatus' => 'available', 'currentDifferenceCents' => 100_000, 'currentRoundedDifferenceEuros' => 1_000], $closed['closure']['afterClosure']);
        self::assertSame([['version' => 1, 'state' => 'closed', 'closedAt' => '2027-02-10T09:30:00+00:00', 'reopenedAt' => null]], $closed['closure']['history']);
    }

    /** @return iterable<string, array{\Closure(self): void, int}> */
    public static function relevantModifications(): iterable
    {
        yield 'salary amount' => [static function (self $t): void {
            $t->salaries->save(new NetTaxableSalary('s-a', self::A, 'ACME', 2026, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'), 2_500_000));
        }, 50_000];
        yield 'new expense' => [static function (self $t): void {
            $t->expenses->save(new ParkingExpense(ExpenseId::fromString('aaaaaaaa-0000-4000-8000-000000000002'), self::A, new \DateTimeImmutable('2026-05-01'), null, 100.00));
        }, 110_000];
        yield 'reintegrated reimbursement' => [static function (self $t): void {
            $t->reimbursements->save(new EmployerReimbursement('r-a', self::A, 'ACME', 2026, 7_000, 'transport', true));
        }, 93_000];
        yield 'fiscal rule' => [static function (self $t): void {
            $config = $t->configs->findByYear(2026);
            \assert(null !== $config);
            $config->defineTaxComparisonRules(12, 50_000, 1_400_000, '2026.2', 'BOFiP-IR-BASE-2026-rectificatif', new \DateTimeImmutable('2026-06-01'));
        }, 60_000];
        yield 'expense attached to another person' => [static function (self $t): void {
            $t->expenses->rows = array_values(array_filter($t->expenses->rows, static fn ($e): bool => 'aaaaaaaa-0000-4000-8000-000000000001' !== $e->id()->value()));
            $t->expenses->save(new ParkingExpense(ExpenseId::fromString('aaaaaaaa-0000-4000-8000-000000000001'), self::B, new \DateTimeImmutable('2026-03-01'), null, 3_000.00));
        }, -200_000];
    }

    /** @param \Closure(self): void $modify */
    #[\PHPUnit\Framework\Attributes\DataProvider('relevantModifications')]
    public function testBeforeClosingEveryRelevantModificationInvalidatesAndRecomputesTheResult(\Closure $modify, int $expectedDifference): void
    {
        $reviewed = $this->read(self::A);
        self::assertSame(100_000, $reviewed['amounts']['differenceCents'] ?? null);

        $modify($this);

        $current = $this->read(self::A);
        self::assertSame($expectedDifference, $current['amounts']['differenceCents'] ?? null, 'the next read is consistent with the current data');
        self::assertNotSame($reviewed['closure']['calculationFingerprint'], $current['closure']['calculationFingerprint']);
        self::assertSame('open', $current['closure']['state']);

        // The calculation the user reviewed is no longer valid: closing it is refused.
        try {
            $this->closeYear(self::A, '2027-02-10T09:30:00+00:00', (string) $reviewed['closure']['calculationFingerprint']);
            self::fail('a stale calculation must not be closed');
        } catch (TaxYearClosureConflictException $e) {
            self::assertStringContainsString('changed since it was validated', $e->getMessage());
        }
        self::assertSame([], $this->closures->rows);

        // The recomputed calculation can be validated and closed.
        $this->closeYear(self::A, '2027-02-10T09:30:00+00:00', (string) $current['closure']['calculationFingerprint']);
        self::assertSame($expectedDifference, $this->read(self::A)['amounts']['differenceCents'] ?? null);
    }

    public function testAfterClosingModificationsNeverReplaceTheSnapshotAndTheDifferenceIsShown(): void
    {
        $this->closeYear(self::A, '2027-02-10T09:30:00+00:00');
        $snapshot = $this->closures->rows[0]->snapshot();
        $fingerprint = $this->closures->rows[0]->fingerprint();

        $this->salaries->save(new NetTaxableSalary('s-a', self::A, 'ACME', 2026, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'), 2_500_000));
        $this->reimbursements->save(new EmployerReimbursement('r-a', self::A, 'ACME', 2026, 7_000, 'transport', true));

        $closed = $this->read(self::A);

        self::assertSame('closed', $closed['status']);
        self::assertSame(2_000_000, $closed['amounts']['netTaxableSalaryCents'] ?? null);
        self::assertSame(100_000, $closed['amounts']['differenceCents'] ?? null);
        self::assertSame(['policy' => 'snapshot_kept_reopen_to_recalculate', 'dataChanged' => true, 'currentStatus' => 'available', 'currentDifferenceCents' => 43_000, 'currentRoundedDifferenceEuros' => 430], $closed['closure']['afterClosure']);
        self::assertSame($snapshot, $this->closures->rows[0]->snapshot());
        self::assertSame($fingerprint, $this->closures->rows[0]->fingerprint());

        // Closing again is refused, even with the current fingerprint: the year must be reopened explicitly.
        try {
            $this->closeYear(self::A, '2027-02-11T09:30:00+00:00', (string) $closed['closure']['calculationFingerprint']);
            self::fail('a closed year cannot be closed again');
        } catch (TaxYearClosureConflictException $e) {
            self::assertStringContainsString('already closed (version 1)', $e->getMessage());
        }
        self::assertCount(1, $this->closures->rows);
    }

    public function testAClosedResultStaysReproducibleWhenTheCurrentFiscalConfigurationChanges(): void
    {
        $this->closeYear(self::A, '2027-02-10T09:30:00+00:00');
        $first = $this->read(self::A);

        $config = $this->configs->findByYear(2026);
        self::assertNotNull($config);
        $config->defineTaxComparisonRules(20, 10_000, 900_000, '2026.9', 'Autre source', new \DateTimeImmutable('2026-09-01'));
        unset($this->configs->rows[2026]);
        $second = $this->read(self::A);

        foreach (['status', 'amounts', 'decision', 'missing', 'rules', 'configuration', 'warnings', 'origins'] as $key) {
            self::assertSame($first[$key], $second[$key], $key);
        }
        self::assertSame(['year' => 2026, 'version' => '2026.1', 'source' => 'BOFiP-IR-BASE-2026', 'effectiveFrom' => '2026-01-01'], $second['configuration']);
        self::assertSame($first['display'], $second['display']);
        $after = $second['closure']['afterClosure'];
        self::assertIsArray($after);
        self::assertTrue($after['dataChanged']);
        self::assertSame('configuration_missing', $after['currentStatus']);
        self::assertNull($after['currentDifferenceCents']);
        self::assertNull($after['currentRoundedDifferenceEuros']);
    }

    public function testExplicitReopeningKeepsTheHistoryAndClosingAgainCreatesANewVersion(): void
    {
        $this->closeYear(self::A, '2027-02-10T09:30:00+00:00');
        $firstSnapshot = $this->closures->rows[0]->snapshot();
        $this->salaries->save(new NetTaxableSalary('s-a', self::A, 'ACME', 2026, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'), 2_500_000));

        try {
            ($this->reopen)(new ReopenTaxYearCommand(self::A, 2026, self::USER, 2, new \DateTimeImmutable('2027-03-01T08:00:00+00:00')));
            self::fail('reopening another version must be refused');
        } catch (TaxYearClosureConflictException $e) {
            self::assertStringContainsString('version 1, not 2', $e->getMessage());
        }

        $reopened = ($this->reopen)(new ReopenTaxYearCommand(self::A, 2026, 'user-2', 1, new \DateTimeImmutable('2027-03-01T08:00:00+00:00')));
        self::assertSame(['personId' => self::A, 'year' => 2026, 'version' => 1, 'state' => 'reopened', 'closedAt' => '2027-02-10T09:30:00+00:00', 'reopenedAt' => '2027-03-01T08:00:00+00:00'], $reopened);
        self::assertSame(1, $this->closures->saves);
        self::assertSame('user-2', $this->closures->rows[0]->reopenedBy());
        self::assertSame($firstSnapshot, $this->closures->rows[0]->snapshot(), 'reopening never destroys the snapshot');

        $open = $this->read(self::A);
        self::assertSame('available', $open['status']);
        self::assertSame(50_000, $open['amounts']['differenceCents'] ?? null, 'the reopened year is recomputed from the current data');
        self::assertSame('open', $open['closure']['state']);
        self::assertTrue($open['closure']['closable']);
        self::assertSame([['version' => 1, 'state' => 'reopened', 'closedAt' => '2027-02-10T09:30:00+00:00', 'reopenedAt' => '2027-03-01T08:00:00+00:00']], $open['closure']['history']);

        try {
            ($this->reopen)(new ReopenTaxYearCommand(self::A, 2026, self::USER, 1, new \DateTimeImmutable('2027-03-02')));
            self::fail('an open year cannot be reopened');
        } catch (TaxYearClosureConflictException $e) {
            self::assertStringContainsString('is not closed', $e->getMessage());
        }

        $second = $this->closeYear(self::A, '2027-03-05T10:00:00+00:00', (string) $open['closure']['calculationFingerprint']);
        self::assertSame(2, $second['version']);
        $closed = $this->read(self::A);
        self::assertSame(2, $closed['closure']['version']);
        self::assertSame(50_000, $closed['amounts']['differenceCents'] ?? null);
        self::assertSame([1, 2], array_column($closed['closure']['history'], 'version'));
        self::assertSame(['reopened', 'closed'], array_column($closed['closure']['history'], 'state'));
        self::assertSame($firstSnapshot, $this->closures->rows[0]->snapshot());
        self::assertSame(100_000, $this->closures->rows[0]->closedResult()->amounts()['differenceCents'] ?? null);
    }

    public function testOnlyAnAvailableCalculationCanBeClosedAndOtherStatusesAreUnchanged(): void
    {
        // incomplete: a reimbursement without reintegration indicator.
        $this->reimbursements->save(new EmployerReimbursement('r-a', self::A, 'ACME', 2026, 7_000, 'transport', null));
        $incomplete = $this->read(self::A);
        self::assertSame('incomplete', $incomplete['status']);
        self::assertSame(['employer_reimbursement[r-a].to_reintegrate'], $incomplete['missing']);
        self::assertNull($incomplete['amounts']);
        self::assertSame('annual_estimate_to_verify', $incomplete['display']['temporalQualification']);
        self::assertSame('open', $incomplete['closure']['state']);
        self::assertFalse($incomplete['closure']['closable']);

        // configuration_missing: no configuration for the year.
        $missing = $this->read(self::A, 2025);
        self::assertSame('configuration_missing', $missing['status']);
        self::assertFalse($missing['closure']['closable']);

        foreach ([[2026, (string) $incomplete['closure']['calculationFingerprint'], 'incomplete'], [2025, (string) $missing['closure']['calculationFingerprint'], 'configuration_missing']] as [$year, $fingerprint, $status]) {
            try {
                ($this->close)(new CloseTaxYearCommand(self::A, $year, self::USER, $fingerprint, new \DateTimeImmutable('2027-02-10')));
                self::fail($status.' must not be closable');
            } catch (TaxYearNotClosableException $e) {
                self::assertStringContainsString('"'.$status.'"', $e->getMessage());
            }
        }
        self::assertSame([], $this->closures->rows);

        // available (person B): unchanged payload apart from the closure block.
        $available = $this->read(self::B);
        self::assertSame('available', $available['status']);
        self::assertSame(-200_000, $available['amounts']['differenceCents'] ?? null);
        self::assertSame('less_advantageous', $available['display']['outcome']);
        self::assertFalse($available['display']['showBanner']);
    }

    public function testClosingIsIsolatedPerPerson(): void
    {
        $this->closeYear(self::A, '2027-02-10T09:30:00+00:00');

        $b = $this->read(self::B);
        self::assertSame('available', $b['status']);
        self::assertSame('open', $b['closure']['state']);
        self::assertSame([], $b['closure']['history']);

        // A leaking repository returning every closure whatever the person must not close B.
        $inner = $this->closures;
        $leaking = new class($inner) implements TaxYearClosureRepositoryInterface {
            public function __construct(private InMemoryTaxYearClosures $inner)
            {
            }

            public function add(TaxYearClosure $closure): void
            {
                $this->inner->add($closure);
            }

            public function save(TaxYearClosure $closure): void
            {
            }

            public function findActive(string $personId, int $year): ?TaxYearClosure
            {
                return $this->inner->rows[0] ?? null;
            }

            public function findHistory(string $personId, int $year): array
            {
                return $this->inner->rows;
            }
        };
        $kilometric = new KilometricAllowanceCalculator($this->createStub(BaremeKilometriqueRepositoryInterface::class));
        $leakingQuery = new GetTaxComparisonQueryHandler($this->salaries, $this->reimbursements, $this->expenses, $this->configs, $kilometric, closures: $leaking);
        $bThroughLeak = $leakingQuery(new GetTaxComparisonQuery(self::B, 2026, new \DateTimeImmutable(self::TODAY)));
        self::assertSame('available', $bThroughLeak['status']);
        self::assertSame(-200_000, $bThroughLeak['amounts']['differenceCents'] ?? null);
        self::assertSame('open', $bThroughLeak['closure']['state']);
        self::assertSame([], $bThroughLeak['closure']['history']);
        try {
            (new ReopenTaxYearCommandHandler($leaking))(new ReopenTaxYearCommand(self::B, 2026, self::USER, 1, new \DateTimeImmutable('2027-03-01')));
            self::fail('person A closure must not be reopened through person B');
        } catch (TaxYearClosureConflictException) {
        }
        self::assertTrue($this->closures->rows[0]->isClosed());

        $this->closeYear(self::B, '2027-02-10T09:30:00+00:00');
        self::assertSame(-200_000, $this->read(self::B)['amounts']['differenceCents'] ?? null);
        self::assertSame(100_000, $this->read(self::A)['amounts']['differenceCents'] ?? null);
        self::assertSame(1, $this->closures->rows[1]->version(), 'versions are numbered per person and year');
        self::assertSame([['id' => 's-b', 'employer' => 'Beta', 'periodStart' => '2026-01-01', 'periodEnd' => '2026-12-31', 'amountCents' => 3_000_000]], $this->closures->rows[1]->snapshot()['retainedData']['netTaxableSalaries']);
    }

    public function testConcurrentClosingsOfTheSameYearCannotBothSucceed(): void
    {
        $fingerprint = (string) $this->read(self::A)['closure']['calculationFingerprint'];
        $this->closeYear(self::A, '2027-02-10T09:30:00+00:00', $fingerprint);

        // Second request with the same validated calculation, after the first one committed.
        try {
            $this->closeYear(self::A, '2027-02-10T09:30:01+00:00', $fingerprint);
            self::fail('the second closing must be refused');
        } catch (TaxYearClosureConflictException) {
        }

        // Second request that read the state BEFORE the first one committed: the storage rejects the duplicate version.
        $this->closures->staleReads = true;
        try {
            $this->closeYear(self::A, '2027-02-10T09:30:02+00:00', $fingerprint);
            self::fail('the duplicate version must be rejected by the storage');
        } catch (TaxYearClosureConflictException $e) {
            self::assertStringContainsString('already closed (version 1)', $e->getMessage());
        }
        self::assertCount(1, $this->closures->rows);
        self::assertSame('2027-02-10T09:30:00+00:00', $this->closures->rows[0]->snapshot()['closedAt']);
    }

    public function testSnapshotRoundTripRebuildsTheSameResultWhateverTheJsonKeyOrder(): void
    {
        $this->closeYear(self::A, '2027-02-10T09:30:00+00:00');
        $calculation = $this->closures->rows[0]->snapshot()['calculation'];
        $original = TaxComparisonResult::fromArray($calculation)->toArray();

        // A JSON column (MySQL) may return object keys in another order.
        $shuffled = $calculation;
        self::assertIsArray($shuffled['amounts']);
        ksort($shuffled['amounts']);
        ksort($shuffled);

        self::assertSame($original, TaxComparisonResult::fromArray($shuffled)->toArray());
        self::assertSame($calculation, $original);

        $this->expectException(\InvalidArgumentException::class);
        TaxComparisonResult::fromArray(['status' => 'closed', 'personId' => self::A, 'year' => 2026]);
    }

    public function testTheEntityRefusesANonAvailableOrForeignCalculation(): void
    {
        $result = TaxComparisonResult::incomplete(self::A, 2026, ['net_taxable_salary[2026]'], null, []);
        try {
            TaxYearClosure::close('id', self::A, 2026, 1, self::USER, new \DateTimeImmutable(), $result, [], [], str_repeat('a', 64));
            self::fail('incomplete must not be closable');
        } catch (TaxYearNotClosableException) {
        }

        $available = $this->calculator->compute(self::A, 2026);
        $this->expectException(\InvalidArgumentException::class);
        TaxYearClosure::close('id', self::B, 2026, 1, self::USER, new \DateTimeImmutable(), $available->result, $available->origins, $available->retainedData, $available->fingerprint);
    }

    /** @return array<string, mixed> */
    private function closeYear(string $personId, string $at, ?string $fingerprint = null): array
    {
        $fingerprint ??= $this->calculator->compute($personId, 2026)->fingerprint;

        return ($this->close)(new CloseTaxYearCommand($personId, 2026, self::USER, $fingerprint, new \DateTimeImmutable($at)));
    }

    /** @return array<string, mixed> */
    private function read(string $personId, int $year = 2026): array
    {
        return ($this->query)(new GetTaxComparisonQuery($personId, $year, new \DateTimeImmutable(self::TODAY)));
    }
}
