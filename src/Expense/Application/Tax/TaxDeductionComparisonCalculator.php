<?php

declare(strict_types=1);

namespace App\Expense\Application\Tax;

final readonly class TaxDeductionComparisonCalculator
{
    /**
     * Plafond connu pour la déduction forfaitaire de 10 %.
     * TODO: déplacer ces valeurs dans FiscalConfig lorsque l'admin fiscal portera aussi le forfait 10 %.
     */
    private const DEFAULT_TEN_PERCENT_CAP = 14171.0;

    /** Minimum courant utilisé à titre indicatif si les revenus sont positifs. */
    private const DEFAULT_TEN_PERCENT_MIN = 509.0;

    /** @return array<string, mixed> */
    public function compare(float $taxableSalary, float $realExpensesTotal): array
    {
        $tenPercentDeduction = $this->calculateTenPercentDeduction($taxableSalary);
        $difference = round($realExpensesTotal - $tenPercentDeduction, 2);

        return [
            'taxableSalary' => round($taxableSalary, 2),
            'realExpensesTotal' => round($realExpensesTotal, 2),
            'tenPercentDeduction' => $tenPercentDeduction,
            'difference' => $difference,
            'recommendedOption' => $difference > 0 ? 'real_expenses' : 'standard_deduction',
            'label' => $difference > 0
                ? sprintf('Frais réels avantageux : +%s € vs forfait 10 %%', number_format($difference, 2, ',', ' '))
                : sprintf('Forfait 10 %% plus avantageux de %s €', number_format(abs($difference), 2, ',', ' ')),
            'warnings' => [
                'Estimation indicative ne remplaçant pas un conseil fiscal personnalisé.',
                'Vérifiez les remboursements employeur et conservez vos justificatifs.',
            ],
        ];
    }

    private function calculateTenPercentDeduction(float $taxableSalary): float
    {
        if ($taxableSalary <= 0.0) {
            return 0.0;
        }

        return round(min(max($taxableSalary * 0.10, self::DEFAULT_TEN_PERCENT_MIN), self::DEFAULT_TEN_PERCENT_CAP), 2);
    }
}
