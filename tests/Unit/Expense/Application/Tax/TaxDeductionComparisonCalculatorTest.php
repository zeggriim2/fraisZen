<?php

declare(strict_types=1);

use App\Expense\Application\Tax\TaxDeductionComparisonCalculator;

it('recommande les frais réels quand ils dépassent le forfait de 10 pour cent', function () {
    $comparison = (new TaxDeductionComparisonCalculator())->compare(30_000.0, 4_250.0);

    expect($comparison['taxableSalary'])->toBe(30_000.0);
    expect($comparison['realExpensesTotal'])->toBe(4_250.0);
    expect($comparison['tenPercentDeduction'])->toBe(3_000.0);
    expect($comparison['difference'])->toBe(1_250.0);
    expect($comparison['recommendedOption'])->toBe('real_expenses');
    expect($comparison['label'])->toContain('Frais réels avantageux');
});

it('recommande le forfait standard quand il est supérieur aux frais réels', function () {
    $comparison = (new TaxDeductionComparisonCalculator())->compare(40_000.0, 2_500.0);

    expect($comparison['tenPercentDeduction'])->toBe(4_000.0);
    expect($comparison['difference'])->toBe(-1_500.0);
    expect($comparison['recommendedOption'])->toBe('standard_deduction');
    expect($comparison['label'])->toContain('Forfait 10 % plus avantageux');
});

it('applique le minimum forfaitaire quand les revenus imposables sont faibles mais positifs', function () {
    $comparison = (new TaxDeductionComparisonCalculator())->compare(2_000.0, 300.0);

    expect($comparison['tenPercentDeduction'])->toBe(509.0);
    expect($comparison['difference'])->toBe(-209.0);
    expect($comparison['recommendedOption'])->toBe('standard_deduction');
});

it('applique le plafond du forfait de 10 pour cent pour les hauts revenus', function () {
    $comparison = (new TaxDeductionComparisonCalculator())->compare(200_000.0, 16_000.0);

    expect($comparison['tenPercentDeduction'])->toBe(14_171.0);
    expect($comparison['difference'])->toBe(1_829.0);
    expect($comparison['recommendedOption'])->toBe('real_expenses');
});

it('retourne une comparaison neutre quand les revenus imposables sont nuls', function () {
    $comparison = (new TaxDeductionComparisonCalculator())->compare(0.0, 0.0);

    expect($comparison['tenPercentDeduction'])->toBe(0.0);
    expect($comparison['difference'])->toBe(0.0);
    expect($comparison['recommendedOption'])->toBe('standard_deduction');
    expect($comparison['warnings'])->not->toBeEmpty();
});
