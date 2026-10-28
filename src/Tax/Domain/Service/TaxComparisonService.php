<?php

declare(strict_types=1);

namespace App\Tax\Domain\Service;

use App\Admin\Domain\Entity\FiscalConfig;
use App\Expense\Domain\Entity\Expense;
use App\Expense\Domain\Entity\MealExpense;
use App\Tax\Domain\Entity\EmployerReimbursement;
use App\Tax\Domain\Entity\NetTaxableSalary;

/**
 * Deterministic comparison between the 10 % forfait and the real expenses option,
 * for one person and one year, using integer cents only.
 *
 *  - theoretical forfait = floor(net taxable salary x rate / 100)
 *  - final forfait       = theoretical forfait bounded by the year's minimum and maximum
 *  - base forfait        = net taxable salary - final forfait
 *  - base real expenses  = net taxable salary + reintegrated reimbursements - real expenses
 *  - difference          = base forfait - base real expenses (> 0: real expenses are advantageous)
 *
 * Only net taxable salaries are consolidated (never gross, paid net, reference tax income or
 * expense totals). Every collection is filtered by person AND year inside the service, so mixed
 * caller-provided data can never leak into another person's result. The fiscal configuration is
 * only used when it is complete and belongs to the requested year: no other year is used as a fallback.
 *
 * @psalm-import-type Amounts from TaxComparisonResult
 * @psalm-import-type Rule from TaxComparisonResult
 *
 * @psalm-suppress UnusedClass
 */
final class TaxComparisonService
{
    /**
     * @param list<NetTaxableSalary>      $salaries
     * @param list<EmployerReimbursement> $reimbursements
     * @param list<Expense>               $expenses
     */
    public function compare(string $personId, int $year, array $salaries, array $reimbursements, array $expenses, ?FiscalConfig $config): TaxComparisonResult
    {
        $salaries = array_values(array_filter(
            $salaries,
            static fn (NetTaxableSalary $s): bool => $s->personId() === $personId && $s->year() === $year,
        ));
        $reimbursements = array_values(array_filter(
            $reimbursements,
            static fn (EmployerReimbursement $r): bool => $r->personId() === $personId && $r->year() === $year,
        ));
        /** @var array<string, Expense> $personExpenses */
        $personExpenses = [];
        foreach ($expenses as $expense) {
            if ($expense->personId() === $personId) {
                $personExpenses[$expense->id()->value()] = $expense;
            }
        }

        $dataMissing = $this->missingData($year, $salaries, $reimbursements, $personExpenses);
        $warnings = [];

        $configMissing = [];
        if (null === $config || $config->year() !== $year) {
            if (null !== $config) {
                $warnings[] = sprintf('The fiscal configuration of year %d was ignored: only the configuration of year %d can be used.', $config->year(), $year);
            }
            $configMissing[] = sprintf('fiscal_config[%d]', $year);
            $config = null;
        } else {
            foreach ($config->missingTaxComparisonFields() as $field) {
                $configMissing[] = sprintf('fiscal_config[%d].%s', $year, $field);
            }
        }

        if ([] !== $configMissing) {
            return TaxComparisonResult::configurationMissing($personId, $year, [...$configMissing, ...$dataMissing], $config?->metadata(), $warnings);
        }
        \assert(null !== $config);

        $rate = $config->forfaitRatePercent();
        $minimum = $config->forfaitMinimumCents();
        $maximum = $config->forfaitMaximumCents();
        $version = $config->version();
        $source = $config->source();
        $effectiveFrom = $config->effectiveFrom();
        \assert(null !== $rate && null !== $minimum && null !== $maximum && null !== $version && null !== $source && null !== $effectiveFrom);

        if ($minimum > $maximum) {
            return TaxComparisonResult::configurationMissing($personId, $year, [sprintf('fiscal_config[%d].forfait_limits', $year), ...$dataMissing], $config->metadata(), $warnings);
        }
        if ([] !== $dataMissing) {
            return TaxComparisonResult::incomplete($personId, $year, $dataMissing, $config->metadata(), $warnings);
        }

        $salaryCents = 0;
        foreach ($salaries as $salary) {
            $salaryCents += $salary->amountCents();
        }

        $realExpensesCents = 0;
        foreach ($personExpenses as $expense) {
            if ((int) $expense->date()->format('Y') === $year) {
                $realExpensesCents += self::toCents($expense->amount());
            }
        }

        [$reintegratedCents, $excludedCents, $reimbursementWarnings] = $this->reintegratedReimbursements($reimbursements, $personExpenses);
        $warnings = [...$warnings, ...$reimbursementWarnings];

        $theoreticalCents = intdiv($salaryCents * $rate, 100);
        $limitApplied = 'none';
        $forfaitCents = $theoreticalCents;
        if ($theoreticalCents < $minimum) {
            $forfaitCents = $minimum;
            $limitApplied = 'minimum';
        } elseif ($theoreticalCents > $maximum) {
            $forfaitCents = $maximum;
            $limitApplied = 'maximum';
        }
        if ($forfaitCents > $salaryCents) {
            $warnings[] = 'The forfait minimum exceeds the net taxable salary: the forfait base is negative.';
        }

        $baseForfaitCents = $salaryCents - $forfaitCents;
        $baseRealCents = $salaryCents + $reintegratedCents - $realExpensesCents;

        /** @var Amounts $amounts */
        $amounts = [
            'netTaxableSalaryCents' => $salaryCents,
            'forfaitRatePercent' => $rate,
            'theoreticalForfaitCents' => $theoreticalCents,
            'forfaitMinimumCents' => $minimum,
            'forfaitMaximumCents' => $maximum,
            'forfaitLimitApplied' => $limitApplied,
            'forfaitCents' => $forfaitCents,
            'realExpensesCents' => $realExpensesCents,
            'reintegratedReimbursementsCents' => $reintegratedCents,
            'excludedAlreadyDeductedContributionCents' => $excludedCents,
            'baseForfaitCents' => $baseForfaitCents,
            'baseRealExpensesCents' => $baseRealCents,
            'differenceCents' => $baseForfaitCents - $baseRealCents,
        ];

        $rule = static fn (string $code, ?int $value): array => [
            'code' => $code,
            'value' => $value,
            'version' => $version,
            'source' => $source,
            'effectiveFrom' => $effectiveFrom->format('Y-m-d'),
        ];
        /** @var list<Rule> $rules */
        $rules = [
            $rule('net_taxable_salary_consolidation', null),
            $rule('forfait_rate_percent', $rate),
            $rule('forfait_minimum_cents', $minimum),
            $rule('forfait_maximum_cents', $maximum),
            $rule('base_forfait_salary_minus_forfait', null),
            $rule('base_real_expenses_salary_plus_reintegrated_minus_expenses', null),
            $rule('reintegration_explicit_only', null),
            $rule('meal_employer_contribution_not_counted_twice', null),
        ];

        return TaxComparisonResult::available($personId, $year, $amounts, $rules, $config->metadata(), $warnings);
    }

    /**
     * @param list<NetTaxableSalary>      $salaries
     * @param list<EmployerReimbursement> $reimbursements
     * @param array<string, Expense>      $personExpenses
     *
     * @return list<string>
     */
    private function missingData(int $year, array $salaries, array $reimbursements, array $personExpenses): array
    {
        $missing = [];
        if ([] === $salaries) {
            $missing[] = sprintf('net_taxable_salary[%d]', $year);
        }
        foreach ($reimbursements as $reimbursement) {
            if (null === $reimbursement->toReintegrate()) {
                $missing[] = sprintf('employer_reimbursement[%s].to_reintegrate', $reimbursement->id());
            }
            $expenseId = $reimbursement->expenseId();
            // The linked expense is only needed when the amount may be reintegrated (deduplication).
            if (null !== $expenseId && false !== $reimbursement->toReintegrate() && !isset($personExpenses[$expenseId])) {
                $missing[] = sprintf('employer_reimbursement[%s].expense[%s]', $reimbursement->id(), $expenseId);
            }
        }

        return $missing;
    }

    /**
     * A meal expense already deducts the employer ticket contribution from its deductible amount.
     * Reintegrating that same contribution would count it twice, so the part of a reimbursement
     * covered by the contribution effectively deducted from the linked meal is excluded.
     *
     * @param list<EmployerReimbursement> $reimbursements
     * @param array<string, Expense>      $personExpenses
     *
     * @return array{int, int, list<string>}
     */
    private function reintegratedReimbursements(array $reimbursements, array $personExpenses): array
    {
        $reintegrated = 0;
        $excluded = 0;
        $warnings = [];
        $ignored = 0;
        /** @var array<string, int> $remainingContribution */
        $remainingContribution = [];

        foreach ($reimbursements as $reimbursement) {
            if (true !== $reimbursement->toReintegrate()) {
                ++$ignored;
                continue;
            }
            $amount = $reimbursement->amountCents();
            $expenseId = $reimbursement->expenseId();
            $expense = null !== $expenseId ? ($personExpenses[$expenseId] ?? null) : null;
            if (null !== $expenseId && $expense instanceof MealExpense && !$expense->withoutReceipt()) {
                $remainingContribution[$expenseId] ??= min(
                    self::toCents($expense->employerTicketContribution()),
                    max(0, self::toCents($expense->mealAmount()) - self::toCents($expense->homeMealValue())),
                );
                $alreadyDeducted = min($amount, $remainingContribution[$expenseId]);
                if ($alreadyDeducted > 0) {
                    $remainingContribution[$expenseId] -= $alreadyDeducted;
                    $amount -= $alreadyDeducted;
                    $excluded += $alreadyDeducted;
                    $warnings[] = sprintf(
                        'Reimbursement %s: %d cents already deducted as employer contribution of meal expense %s were not counted twice.',
                        $reimbursement->id(),
                        $alreadyDeducted,
                        $expenseId,
                    );
                }
            }
            $reintegrated += $amount;
        }
        if ($ignored > 0) {
            $warnings[] = sprintf('%d reimbursement(s) not marked for reintegration were not added to the real expenses base.', $ignored);
        }

        return [$reintegrated, $excluded, $warnings];
    }

    private static function toCents(float $amount): int
    {
        return (int) round($amount * 100.0);
    }
}
