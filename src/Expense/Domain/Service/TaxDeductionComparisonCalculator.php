<?php

declare(strict_types=1);

namespace App\Expense\Domain\Service;

final readonly class TaxDeductionComparisonCalculator
{
    private const RATE = 0.10;

    /** @var array<int, float> */
    private const CAPS_BY_YEAR = [
        2023 => 13_522.0,
        2024 => 14_171.0,
        2025 => 14_555.0,
        2026 => 14_777.0,
    ];

    /** @return array<string, mixed> */
    public function compare(float $taxableSalary, float $realExpensesTotal, int $year): array
    {
        $cap = $this->capForYear($year);
        $standardDeduction = round(min(max($taxableSalary * self::RATE, 0.0), $cap), 2);
        $realExpensesTotal = round(max($realExpensesTotal, 0.0), 2);
        $difference = round($realExpensesTotal - $standardDeduction, 2);
        $recommendedOption = $difference > 0 ? 'real_expenses' : 'standard_deduction';

        return [
            'taxableSalary' => round(max($taxableSalary, 0.0), 2),
            'realExpensesTotal' => $realExpensesTotal,
            'standardDeductionRate' => self::RATE,
            'standardDeductionCap' => $cap,
            'standardDeduction' => $standardDeduction,
            'difference' => $difference,
            'recommendedOption' => $recommendedOption,
            'label' => 'real_expenses' === $recommendedOption
                ? sprintf('Frais réels avantageux : +%s € vs forfait 10 %%', $this->formatAmount($difference))
                : sprintf('Forfait 10 %% plus avantageux de %s €', $this->formatAmount(abs($difference))),
            'warnings' => [
                'Estimation indicative : ce résultat ne constitue pas un conseil fiscal.',
                'Vérifiez les remboursements employeur et conservez vos justificatifs.',
            ],
        ];
    }

    private function capForYear(int $year): float
    {
        foreach (array_reverse(self::CAPS_BY_YEAR, true) as $knownYear => $cap) {
            if ($year >= $knownYear) {
                return $cap;
            }
        }

        return self::CAPS_BY_YEAR[2023];
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, ',', ' ');
    }
}
