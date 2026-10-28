<?php

declare(strict_types=1);

namespace App\Tax\Domain\Service;

use App\Admin\Domain\Entity\FiscalConfig;
use App\Expense\Domain\Entity\Expense;
use App\Tax\Domain\Entity\EmployerReimbursement;
use App\Tax\Domain\Entity\NetTaxableSalary;

/** @psalm-suppress UnusedClass */
final class TaxComparisonService
{
    /**
     * @param list<NetTaxableSalary>      $salaries
     * @param list<EmployerReimbursement> $reimbursements
     * @param list<Expense>               $expenses
     */
    public function compare(string $personId, int $year, array $salaries, array $reimbursements, array $expenses, ?FiscalConfig $config): TaxComparisonResult
    {
        $salaryCents = array_sum(array_map(fn (NetTaxableSalary $s): int => $s->amountCents(), array_filter($salaries, fn (NetTaxableSalary $s): bool => $s->personId() === $personId && $s->year() === $year)));
        $missing = [];
        if (0 === count(array_filter($salaries, fn (NetTaxableSalary $s): bool => $s->personId() === $personId && $s->year() === $year))) {
            $missing[] = 'net_taxable_salary';
        }
        $matchingReimbursements = array_filter($reimbursements, fn (EmployerReimbursement $r): bool => $r->personId() === $personId && $r->year() === $year);
        if (null === $config || !$config->isComplete()) {
            return new TaxComparisonResult('configuration_missing', [], array_values(array_unique($missing)), [], null, ['No fiscal configuration was silently substituted.']);
        }
        if ($missing) {
            return new TaxComparisonResult('incomplete', [], array_values(array_unique($missing)));
        }

        $reintegrated = array_sum(array_map(fn (EmployerReimbursement $r): int => $r->toReintegrate() ? $r->amountCents() : 0, $matchingReimbursements));
        $realExpenses = array_sum(array_map(fn (Expense $e): int => (int) round($e->amount() * 100.0), array_filter($expenses, fn (Expense $e): bool => $e->personId() === $personId && (int) $e->date()->format('Y') === $year)));
        $theoretical = intdiv($salaryCents * $config->forfaitRatePercent(), 100);
        $forfait = max($config->forfaitMinimumCents(), min($theoretical, $config->forfaitMaximumCents()));
        $baseForfait = $salaryCents - $forfait;
        $baseReal = $salaryCents + $reintegrated - $realExpenses;
        $difference = $baseForfait - $baseReal;

        return new TaxComparisonResult('available', ['salaryCents' => $salaryCents, 'reintegratedReimbursementsCents' => $reintegrated, 'realExpensesCents' => $realExpenses, 'theoreticalForfaitCents' => $theoretical, 'forfaitCents' => $forfait, 'baseForfaitCents' => $baseForfait, 'baseRealExpensesCents' => $baseReal, 'differenceCents' => $difference, 'decision' => $difference > 0 ? 'advantageous' : ($difference < 0 ? 'less_advantageous' : 'equivalent')], ['10_percent_of_net_taxable_salary', 'forfait_minimum_and_maximum_applied', 'reintegrated_reimbursements_added_to_real_expenses_base', 'real_expenses_are_independent'], [], $config->metadata(), ['Only net taxable salaries are consolidated; reimbursements and real expenses are kept separate.']);
    }
}
