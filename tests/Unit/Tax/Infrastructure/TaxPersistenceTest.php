<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Infrastructure;

use App\Admin\Domain\Entity\FiscalConfig;
use App\Admin\Infrastructure\Persistence\DoctrineFiscalConfigRepository;
use App\Expense\Domain\Entity\MealExpense;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\Tax\Domain\Entity\EmployerReimbursement;
use App\Tax\Domain\Entity\NetTaxableSalary;
use App\Tax\Domain\Service\TaxComparisonService;
use App\Tax\Infrastructure\Persistence\DoctrineEmployerReimbursementRepository;
use App\Tax\Infrastructure\Persistence\DoctrineNetTaxableSalaryRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

/**
 * Persistence integration: real Doctrine attribute mappings, round-tripped through an in-memory SQLite database.
 */
final class TaxPersistenceTest extends TestCase
{
    private EntityManager $em;

    protected function setUp(): void
    {
        $root = \dirname(__DIR__, 4);
        $config = ORMSetup::createAttributeMetadataConfig([$root.'/src/Tax/Domain/Entity', $root.'/src/Admin/Domain/Entity'], true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(\CASE_LOWER, true));
        $config->enableNativeLazyObjects(true);
        $this->em = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);
        (new SchemaTool($this->em))->createSchema([
            $this->em->getClassMetadata(FiscalConfig::class),
            $this->em->getClassMetadata(NetTaxableSalary::class),
            $this->em->getClassMetadata(EmployerReimbursement::class),
        ]);
    }

    public function testSalariesAreStoredPerPersonEmployerPeriodAndYear(): void
    {
        $repository = new DoctrineNetTaxableSalaryRepository($this->em);
        $repository->save(new NetTaxableSalary('s2', 'person-a', 'Employer B', 2026, new \DateTimeImmutable('2026-07-01'), new \DateTimeImmutable('2026-12-31'), 1_200_001));
        $repository->save(new NetTaxableSalary('s1', 'person-a', 'Employer A', 2026, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-06-30'), 1_000_000));
        $repository->save(new NetTaxableSalary('s3', 'person-b', 'Employer A', 2026, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'), 9_000_000));
        $repository->save(new NetTaxableSalary('s4', 'person-a', 'Employer A', 2025, new \DateTimeImmutable('2025-01-01'), new \DateTimeImmutable('2025-12-31'), 8_000_000));
        $this->em->clear();

        $found = $repository->findByPersonAndYear('person-a', 2026);

        self::assertSame(['s1', 's2'], array_map(static fn (NetTaxableSalary $s): string => $s->id(), $found));
        self::assertSame('Employer B', $found[1]->employer());
        self::assertSame('2026-07-01', $found[1]->periodStart()->format('Y-m-d'));
        self::assertSame('2026-12-31', $found[1]->periodEnd()->format('Y-m-d'));
        self::assertSame(1_200_001, $found[1]->amountCents());
        self::assertSame(2026, $found[1]->year());
        self::assertSame('person-a', $found[1]->personId());
    }

    public function testReimbursementsKeepEveryAttributeIncludingUnknownIndicatorAndLinkedExpense(): void
    {
        $repository = new DoctrineEmployerReimbursementRepository($this->em);
        $repository->save(new EmployerReimbursement('r1', 'person-a', 'Employer A', 2026, 1_234, 'meal', true, new \DateTimeImmutable('2026-03-15'), '11111111-1111-4111-8111-111111111111'));
        $repository->save(new EmployerReimbursement('r2', 'person-a', 'Employer B', 2026, 5_000, 'transport', null));
        $repository->save(new EmployerReimbursement('r3', 'person-a', 'Employer B', 2026, 700, 'telework', false, new \DateTimeImmutable('2026-01-10')));
        $repository->save(new EmployerReimbursement('r4', 'person-b', 'Employer B', 2026, 700, 'telework', true));
        $this->em->clear();

        $byId = [];
        foreach ($repository->findByPersonAndYear('person-a', 2026) as $reimbursement) {
            $byId[$reimbursement->id()] = $reimbursement;
        }

        // Ordered by date (unknown date first) then id; person-b is never returned.
        self::assertSame(['r2', 'r3', 'r1'], array_keys($byId));
        self::assertSame([1_234, 'Employer A', '2026-03-15', 'meal', true, '11111111-1111-4111-8111-111111111111', 2026], [
            $byId['r1']->amountCents(), $byId['r1']->employer(), $byId['r1']->date()?->format('Y-m-d'), $byId['r1']->category(), $byId['r1']->toReintegrate(), $byId['r1']->expenseId(), $byId['r1']->year(),
        ]);
        self::assertNull($byId['r2']->toReintegrate());
        self::assertNull($byId['r2']->date());
        self::assertNull($byId['r2']->expenseId());
        self::assertFalse($byId['r3']->toReintegrate());
    }

    public function testInputsEditedFromTheFiscalDataPanelRoundTrip(): void
    {
        $salaries = new DoctrineNetTaxableSalaryRepository($this->em);
        $reimbursements = new DoctrineEmployerReimbursementRepository($this->em);
        $salaries->save(new NetTaxableSalary('s1', 'person-a', 'Employer A', 2026, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'), 1_000_000));
        $reimbursements->save(new EmployerReimbursement('r1', 'person-a', 'Employer A', 2026, 1_234, 'meal', null, null, '11111111-1111-4111-8111-111111111111'));
        $this->em->clear();

        $reimbursement = $reimbursements->findById('r1');
        self::assertNotNull($reimbursement);
        $reimbursement->indicateReintegration(true);
        $reimbursement->unlinkExpense();
        $reimbursements->save($reimbursement);
        $salary = $salaries->findById('s1');
        self::assertNotNull($salary);
        $salaries->remove($salary);
        $this->em->clear();

        $reloaded = $reimbursements->findById('r1');
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->toReintegrate());
        self::assertNull($reloaded->expenseId());
        self::assertNull($salaries->findById('s1'));
        self::assertNull($reimbursements->findById('unknown'));

        $reimbursements->remove($reloaded);
        $this->em->clear();
        self::assertSame([], $reimbursements->findByPersonAndYear('person-a', 2026));
    }

    public function testVersionedFiscalConfigurationRoundTripAndLegacyRowStaysIncomplete(): void
    {
        $repository = new DoctrineFiscalConfigRepository($this->em);
        $repository->save(new FiscalConfig(2026, '2.70', '5.35', 10, 50_000, 1_400_000, '2026.1', 'BOFiP-IR-BASE', new \DateTimeImmutable('2026-01-01')));
        $repository->save(new FiscalConfig(2025, '2.70', '5.35'));
        $this->em->clear();

        $current = $repository->findByYear(2026);
        $legacy = $repository->findByYear(2025);

        self::assertNotNull($current);
        self::assertNotNull($legacy);
        self::assertTrue($current->isComplete());
        self::assertSame([10, 50_000, 1_400_000], [$current->forfaitRatePercent(), $current->forfaitMinimumCents(), $current->forfaitMaximumCents()]);
        self::assertSame(['year' => 2026, 'version' => '2026.1', 'source' => 'BOFiP-IR-BASE', 'effectiveFrom' => '2026-01-01'], $current->metadata());
        self::assertSame(5.35, $current->homeMealValue());
        self::assertFalse($legacy->isComplete());
        self::assertNull($repository->findByYear(2024));
    }

    public function testComparisonOnPersistedDataIsIsolatedPerPersonAndYear(): void
    {
        $salaries = new DoctrineNetTaxableSalaryRepository($this->em);
        $reimbursements = new DoctrineEmployerReimbursementRepository($this->em);
        $configs = new DoctrineFiscalConfigRepository($this->em);
        $mealId = '11111111-1111-4111-8111-111111111111';
        $salaries->save(new NetTaxableSalary('s1', 'person-a', 'Employer A', 2026, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-06-30'), 1_000_000));
        $salaries->save(new NetTaxableSalary('s2', 'person-a', 'Employer B', 2026, new \DateTimeImmutable('2026-07-01'), new \DateTimeImmutable('2026-12-31'), 1_000_000));
        $salaries->save(new NetTaxableSalary('s3', 'person-b', 'Employer B', 2026, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'), 5_000_000));
        $reimbursements->save(new EmployerReimbursement('r1', 'person-a', 'Employer A', 2026, 1_000, 'meal', true, new \DateTimeImmutable('2026-05-01'), $mealId));
        $reimbursements->save(new EmployerReimbursement('r2', 'person-b', 'Employer B', 2026, 3_000, 'transport', true));
        $configs->save(new FiscalConfig(2026, '2.70', '5.35', 10, 50_000, 1_400_000, '2026.1', 'BOFiP-IR-BASE', new \DateTimeImmutable('2026-01-01')));
        $this->em->clear();

        $meal = new MealExpense(ExpenseId::fromString($mealId), 'person-a', new \DateTimeImmutable('2026-05-01'), null, 20.00, 5.00, 6.00);
        $service = new TaxComparisonService();

        $a = $service->compare('person-a', 2026, $salaries->findByPersonAndYear('person-a', 2026), $reimbursements->findByPersonAndYear('person-a', 2026), [$meal], $configs->findByYear(2026));
        $b = $service->compare('person-b', 2026, $salaries->findByPersonAndYear('person-b', 2026), $reimbursements->findByPersonAndYear('person-b', 2026), [$meal], $configs->findByYear(2026));
        $previousYear = $service->compare('person-a', 2025, $salaries->findByPersonAndYear('person-a', 2025), [], [], $configs->findByYear(2025));

        self::assertSame('available', $a->status());
        self::assertSame([2_000_000, 200_000, 900, 400, 600, 1_800_000, 1_999_500, -199_500], [
            $a->amounts()['netTaxableSalaryCents'] ?? null,
            $a->amounts()['forfaitCents'] ?? null,
            $a->amounts()['realExpensesCents'] ?? null,
            $a->amounts()['reintegratedReimbursementsCents'] ?? null,
            $a->amounts()['excludedAlreadyDeductedContributionCents'] ?? null,
            $a->amounts()['baseForfaitCents'] ?? null,
            $a->amounts()['baseRealExpensesCents'] ?? null,
            $a->amounts()['differenceCents'] ?? null,
        ]);
        self::assertSame('less_advantageous', $a->decision());
        self::assertSame([5_000_000, 500_000, 0, 3_000], [
            $b->amounts()['netTaxableSalaryCents'] ?? null,
            $b->amounts()['forfaitCents'] ?? null,
            $b->amounts()['realExpensesCents'] ?? null,
            $b->amounts()['reintegratedReimbursementsCents'] ?? null,
        ]);
        self::assertSame('configuration_missing', $previousYear->status());
        self::assertSame(['fiscal_config[2025]', 'net_taxable_salary[2025]'], $previousYear->missing());
    }
}
