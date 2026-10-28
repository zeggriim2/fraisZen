<?php

declare(strict_types=1);

namespace App\Tax\Domain\Service;

/**
 * Immutable outcome of a forfait / real expenses comparison for ONE person and ONE year.
 *
 * Built only through the named factories so that values, missing information, rules,
 * configuration metadata and warnings can never be swapped positionally.
 *
 * @psalm-type Amounts = array{
 *     netTaxableSalaryCents: int,
 *     forfaitRatePercent: int,
 *     theoreticalForfaitCents: int,
 *     forfaitMinimumCents: int,
 *     forfaitMaximumCents: int,
 *     forfaitLimitApplied: 'none'|'minimum'|'maximum',
 *     forfaitCents: int,
 *     realExpensesCents: int,
 *     reintegratedReimbursementsCents: int,
 *     excludedAlreadyDeductedContributionCents: int,
 *     baseForfaitCents: int,
 *     baseRealExpensesCents: int,
 *     differenceCents: int
 * }
 * @psalm-type Rule = array{code: string, value: int|null, version: string, source: string, effectiveFrom: string}
 * @psalm-type Configuration = array{year: int, version: ?string, source: ?string, effectiveFrom: ?string}
 *
 * @psalm-suppress PossiblyUnusedMethod
 */
final class TaxComparisonResult
{
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_INCOMPLETE = 'incomplete';
    public const STATUS_CONFIGURATION_MISSING = 'configuration_missing';

    /** Real expenses lower the taxable base: the option is advantageous. */
    public const DECISION_ADVANTAGEOUS = 'advantageous';
    public const DECISION_LESS_ADVANTAGEOUS = 'less_advantageous';
    public const DECISION_EQUIVALENT = 'equivalent';

    /**
     * @param Amounts|null       $amounts
     * @param list<string>       $missing
     * @param list<Rule>         $rules
     * @param Configuration|null $configuration
     * @param list<string>       $warnings
     */
    private function __construct(
        private readonly string $status,
        private readonly string $personId,
        private readonly int $year,
        private readonly ?array $amounts,
        private readonly ?string $decision,
        private readonly array $missing,
        private readonly array $rules,
        private readonly ?array $configuration,
        private readonly array $warnings,
    ) {
    }

    /**
     * @param Amounts       $amounts
     * @param list<Rule>    $rules
     * @param Configuration $configuration
     * @param list<string>  $warnings
     */
    public static function available(string $personId, int $year, array $amounts, array $rules, array $configuration, array $warnings): self
    {
        $difference = $amounts['differenceCents'];
        $decision = match (true) {
            $difference > 0 => self::DECISION_ADVANTAGEOUS,
            $difference < 0 => self::DECISION_LESS_ADVANTAGEOUS,
            default => self::DECISION_EQUIVALENT,
        };

        return new self(self::STATUS_AVAILABLE, $personId, $year, $amounts, $decision, [], $rules, $configuration, $warnings);
    }

    /**
     * @param list<string>       $missing
     * @param Configuration|null $configuration
     * @param list<string>       $warnings
     */
    public static function incomplete(string $personId, int $year, array $missing, ?array $configuration, array $warnings): self
    {
        return new self(self::STATUS_INCOMPLETE, $personId, $year, null, null, $missing, [], $configuration, $warnings);
    }

    /**
     * @param list<string>       $missing
     * @param Configuration|null $configuration
     * @param list<string>       $warnings
     */
    public static function configurationMissing(string $personId, int $year, array $missing, ?array $configuration, array $warnings): self
    {
        return new self(self::STATUS_CONFIGURATION_MISSING, $personId, $year, null, null, $missing, [], $configuration, $warnings);
    }

    public function status(): string
    {
        return $this->status;
    }

    public function personId(): string
    {
        return $this->personId;
    }

    public function year(): int
    {
        return $this->year;
    }

    /** @return Amounts|null */
    public function amounts(): ?array
    {
        return $this->amounts;
    }

    public function decision(): ?string
    {
        return $this->decision;
    }

    /** @return list<string> */
    public function missing(): array
    {
        return $this->missing;
    }

    /** @return list<Rule> */
    public function rules(): array
    {
        return $this->rules;
    }

    /** @return Configuration|null */
    public function configuration(): ?array
    {
        return $this->configuration;
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * @return array{
     *     status: string,
     *     personId: string,
     *     year: int,
     *     amounts: Amounts|null,
     *     decision: string|null,
     *     missing: list<string>,
     *     rules: list<Rule>,
     *     configuration: Configuration|null,
     *     warnings: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'personId' => $this->personId,
            'year' => $this->year,
            'amounts' => $this->amounts,
            'decision' => $this->decision,
            'missing' => $this->missing,
            'rules' => $this->rules,
            'configuration' => $this->configuration,
            'warnings' => $this->warnings,
        ];
    }
}
