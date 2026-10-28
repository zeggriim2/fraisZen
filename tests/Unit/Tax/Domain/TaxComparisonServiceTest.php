<?php

declare(strict_types=1);

namespace App\Tests\Unit\Tax\Domain;

use App\Admin\Domain\Entity\FiscalConfig;
use App\Expense\Domain\Entity\MealExpense;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\Tax\Domain\Entity\EmployerReimbursement;
use App\Tax\Domain\Entity\NetTaxableSalary;
use App\Tax\Domain\Service\TaxComparisonService;
use PHPUnit\Framework\TestCase;

final class TaxComparisonServiceTest extends TestCase
{
    public function testConsolidatesOnePersonAndAppliesLimitsInCents(): void
    {
        $salary = new NetTaxableSalary('s1', 'person-a', 'Employer A', 2026, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-06-30'), 100000);
        $salary2 = new NetTaxableSalary('s2', 'person-a', 'Employer B', 2026, new \DateTimeImmutable('2026-07-01'), new \DateTimeImmutable('2026-12-31'), 500000);
        $other = new NetTaxableSalary('s3', 'person-b', 'Employer C', 2026, new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-12-31'), 999999);
        $mealId = '11111111-1111-4111-8111-111111111111';
        $reimbursement = new EmployerReimbursement('r1', 'person-a', 'Employer A', 2026, 10000, 'meal', true, new \DateTimeImmutable('2026-05-01'), $mealId);
        $ignored = new EmployerReimbursement('r2', 'person-a', 'Employer A', 2026, 90000, 'transport', false);
        $meal = new MealExpense(ExpenseId::fromString($mealId), 'person-a', new \DateTimeImmutable('2026-05-01'), null, 300.00, 5.00, 10.00);
        $config = new FiscalConfig(2026, '2.00', '5.35', 10, 10000, 50000, '2026.1', 'BOFiP', new \DateTimeImmutable('2026-01-01'));

        $result = (new TaxComparisonService())->compare('person-a', 2026, [$salary, $salary2, $other], [$reimbursement, $ignored], [$meal], $config)->toArray();

        self::assertSame('available', $result['status']);
        self::assertSame(600000, $result['salaryCents']);
        self::assertSame(10000, $result['reintegratedReimbursementsCents']);
        self::assertSame(28500, $result['realExpensesCents']);
        self::assertSame(50000, $result['forfaitCents']);
        self::assertSame(550000, $result['baseForfaitCents']);
        self::assertSame(581500, $result['baseRealExpensesCents']);
        self::assertSame(-31500, $result['differenceCents']);
        self::assertSame('less_advantageous', $result['decision']);
    }

    public function testDistinguishesMissingConfigurationAndSalary(): void
    {
        $service = new TaxComparisonService();
        self::assertSame('configuration_missing', $service->compare('person-a', 2026, [], [], [], null)->status());
        $config = new FiscalConfig(2026, '2.00', '5.35', 10, 10000, 50000, 'v1', 'source', new \DateTimeImmutable('2026-01-01'));
        $result = $service->compare('person-a', 2026, [], [], [], $config)->toArray();
        self::assertSame('incomplete', $result['status']);
        self::assertSame(['net_taxable_salary'], $result['missing']);
    }
}