<?php

declare(strict_types=1);

use App\Expense\Domain\Service\TaxDeductionComparisonCalculator;

it('compares real expenses with the year-specific capped standard deduction', function () {
    $calculator = new TaxDeductionComparisonCalculator();

    $result = $calculator->compare(200_000.0, 20_000.0, 2024);

    expect($result)->toMatchArray([
        'realExpensesTotal' => 20_000.0,
        'standardDeductionRate' => 0.10,
        'standardDeductionCap' => 14_171.0,
        'standardDeduction' => 14_171.0,
        'difference' => 5_829.0,
        'recommendedOption' => 'real_expenses',
    ]);
});

it('returns a fiscal estimation warning and recommends the standard deduction when higher', function () {
    $calculator = new TaxDeductionComparisonCalculator();

    $result = $calculator->compare(35_000.0, 1_000.0, 2025);

    expect($result['recommendedOption'])->toBe('standard_deduction')
        ->and($result['warnings'])->toContain('Estimation indicative : ce résultat ne constitue pas un conseil fiscal.');
});
