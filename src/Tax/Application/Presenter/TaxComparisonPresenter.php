<?php

declare(strict_types=1);

namespace App\Tax\Application\Presenter;

use App\Tax\Domain\Service\TaxComparisonResult;

/**
 * Turns one engine result into the payload displayed by the dashboard, the annual summary
 * and the individual context. Every display decision lives here so it is unit-tested once:
 *
 *  - the banner is shown only for an "available" result whose EXACT difference (cents) is > 0;
 *  - the displayed difference is rounded to the nearest euro (half away from zero); an absolute
 *    difference below 1 euro is flagged so the UI shows "écart inférieur à 1 €";
 *  - the year is qualified as a provisional estimate (current or future calendar year) or as an
 *    annual estimate to verify (elapsed year: FraisZen has no year closing yet, so an elapsed year
 *    is never considered closed);
 *  - every missing information code is mapped to a translation key and a direct link to the field.
 *
 * Labels are NOT produced here: the frontend translates the returned keys.
 *
 * @psalm-import-type Rule from TaxComparisonResult
 * @psalm-import-type Amounts from TaxComparisonResult
 * @psalm-import-type Configuration from TaxComparisonResult
 *
 * @psalm-type MissingField = array{code: string, field: string, params: array<string, string>, link: array{path: string, query: array<string, string>, hash: string}|null}
 * @psalm-type Origins = array{
 *     netTaxableSalary: array{entries: int, employers: list<string>},
 *     realExpenses: array{entries: int, trips: int, travelAllowanceCents: int|null},
 *     reimbursements: array{entries: int, reintegrated: int}
 * }
 * @psalm-type Display = array{
 *     temporalQualification: 'provisional_estimate'|'annual_estimate_to_verify',
 *     showBanner: bool,
 *     outcome: 'advantageous'|'equivalent'|'less_advantageous'|null,
 *     roundedDifferenceEuros: int|null,
 *     differenceBelowOneEuro: bool,
 *     missingFields: list<MissingField>,
 *     disclaimerKey: string
 * }
 * @psalm-type Presented = array{
 *     status: string,
 *     personId: string,
 *     year: int,
 *     amounts: Amounts|null,
 *     decision: string|null,
 *     missing: list<string>,
 *     rules: list<Rule>,
 *     configuration: Configuration|null,
 *     warnings: list<string>,
 *     origins: Origins,
 *     display: Display
 * }
 */
final readonly class TaxComparisonPresenter
{
    public const TEMPORAL_PROVISIONAL = 'provisional_estimate';
    public const TEMPORAL_TO_VERIFY = 'annual_estimate_to_verify';
    public const DISCLAIMER_KEY = 'tax.comparison.disclaimer';

    /**
     * @param Origins $origins
     *
     * @return Presented
     */
    public function present(TaxComparisonResult $result, \DateTimeImmutable $today, array $origins): array
    {
        $amounts = $result->amounts();
        $available = TaxComparisonResult::STATUS_AVAILABLE === $result->status() && null !== $amounts;
        $difference = $available ? $amounts['differenceCents'] : null;

        $outcome = null;
        if (null !== $difference) {
            $outcome = match (true) {
                $difference > 0 => 'advantageous',
                $difference < 0 => 'less_advantageous',
                default => 'equivalent',
            };
        }

        return [
            ...$result->toArray(),
            'origins' => $origins,
            'display' => [
                'temporalQualification' => self::temporalQualification($result->year(), $today),
                'showBanner' => null !== $difference && $difference > 0,
                'outcome' => $outcome,
                'roundedDifferenceEuros' => null === $difference ? null : self::roundToEuro($difference),
                'differenceBelowOneEuro' => null !== $difference && 0 !== $difference && abs($difference) < 100,
                'missingFields' => array_map(
                    static fn (string $code): array => self::missingField($code, $result->personId(), $result->year()),
                    $result->missing(),
                ),
                'disclaimerKey' => self::DISCLAIMER_KEY,
            ],
        ];
    }

    /** @return 'provisional_estimate'|'annual_estimate_to_verify' */
    public static function temporalQualification(int $year, \DateTimeImmutable $today): string
    {
        return $year >= (int) $today->format('Y') ? self::TEMPORAL_PROVISIONAL : self::TEMPORAL_TO_VERIFY;
    }

    /** Nearest euro, halves rounded away from zero, computed on integers only. */
    public static function roundToEuro(int $cents): int
    {
        $rounded = intdiv(abs($cents) + 50, 100);

        return $cents < 0 ? -$rounded : $rounded;
    }

    /** @return MissingField */
    public static function missingField(string $code, string $personId, int $year): array
    {
        $y = (string) $year;
        $hash = static fn (string $field): string => 'tax-field-'.str_replace('_', '-', $field);

        if (1 === preg_match('/^net_taxable_salary\[\d{4}\]$/', $code)) {
            return self::field($code, 'net_taxable_salary', [], '/persons', ['personId' => $personId, 'year' => $y, 'field' => 'net_taxable_salary'], $hash('net_taxable_salary'));
        }
        if (1 === preg_match('/^employer_reimbursement\[([^\]]+)\]\.to_reintegrate$/', $code, $m)) {
            return self::field($code, 'employer_reimbursement_to_reintegrate', ['reimbursementId' => $m[1]], '/persons', ['personId' => $personId, 'year' => $y, 'field' => 'employer_reimbursement', 'reimbursementId' => $m[1]], $hash('employer_reimbursement'));
        }
        if (1 === preg_match('/^employer_reimbursement\[([^\]]+)\]\.expense\[([^\]]+)\]$/', $code, $m)) {
            return self::field($code, 'employer_reimbursement_expense', ['reimbursementId' => $m[1], 'expenseId' => $m[2]], '/calendar', ['personId' => $personId, 'expenseId' => $m[2]], $hash('expense'));
        }
        if (1 === preg_match('/^travel_allowance\[\d{4}\]$/', $code)) {
            return self::field($code, 'travel_allowance', [], '/trips', ['personId' => $personId, 'year' => $y], $hash('travel_allowance'));
        }
        if (1 === preg_match('/^fiscal_config\[\d{4}\]$/', $code)) {
            return self::field($code, 'fiscal_config', [], '/admin/fiscal-config', ['year' => $y], $hash('fiscal_config'));
        }
        if (1 === preg_match('/^fiscal_config\[\d{4}\]\.([a-z_]+)$/', $code, $m)) {
            return self::field($code, 'fiscal_config_'.$m[1], [], '/admin/fiscal-config', ['year' => $y, 'field' => $m[1]], $hash('fiscal_config_'.$m[1]));
        }

        return ['code' => $code, 'field' => 'unknown', 'params' => [], 'link' => null];
    }

    /**
     * @param array<string, string> $params
     * @param array<string, string> $query
     *
     * @return MissingField
     */
    private static function field(string $code, string $field, array $params, string $path, array $query, string $hash): array
    {
        return ['code' => $code, 'field' => $field, 'params' => $params, 'link' => ['path' => $path, 'query' => $query, 'hash' => '#'.$hash]];
    }
}
