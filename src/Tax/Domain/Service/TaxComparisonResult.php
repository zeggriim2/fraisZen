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

    /**
     * Rebuilds a result from its serialized form (toArray()), e.g. from a closure snapshot.
     * It goes through the same named factories, so a stored result keeps its invariants, and the
     * amounts are re-keyed in their canonical order (a JSON store may reorder object keys).
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $personId = $data['personId'] ?? null;
        $year = $data['year'] ?? null;
        $status = $data['status'] ?? null;
        if (!\is_string($personId) || !\is_int($year) || !\is_string($status)) {
            throw new \InvalidArgumentException('Invalid serialized comparison result.');
        }
        $configuration = self::configurationFrom($data['configuration'] ?? null);
        $warnings = self::stringList($data['warnings'] ?? []);

        return match ($status) {
            self::STATUS_AVAILABLE => self::available(
                $personId,
                $year,
                self::amountsFrom($data['amounts'] ?? null),
                self::rulesFrom($data['rules'] ?? []),
                $configuration ?? throw new \InvalidArgumentException('An available result has a configuration.'),
                $warnings,
            ),
            self::STATUS_INCOMPLETE => self::incomplete($personId, $year, self::stringList($data['missing'] ?? []), $configuration, $warnings),
            self::STATUS_CONFIGURATION_MISSING => self::configurationMissing($personId, $year, self::stringList($data['missing'] ?? []), $configuration, $warnings),
            default => throw new \InvalidArgumentException(\sprintf('Unknown comparison status "%s".', $status)),
        };
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

    /** @return Amounts */
    private static function amountsFrom(mixed $value): array
    {
        if (!\is_array($value)) {
            throw new \InvalidArgumentException('An available result has amounts.');
        }
        $int = static function (string $key) use ($value): int {
            $v = $value[$key] ?? null;
            if (!\is_int($v)) {
                throw new \InvalidArgumentException(\sprintf('Invalid amount "%s".', $key));
            }

            return $v;
        };
        $limit = $value['forfaitLimitApplied'] ?? null;
        if ('none' !== $limit && 'minimum' !== $limit && 'maximum' !== $limit) {
            throw new \InvalidArgumentException('Invalid forfait limit.');
        }

        return [
            'netTaxableSalaryCents' => $int('netTaxableSalaryCents'),
            'forfaitRatePercent' => $int('forfaitRatePercent'),
            'theoreticalForfaitCents' => $int('theoreticalForfaitCents'),
            'forfaitMinimumCents' => $int('forfaitMinimumCents'),
            'forfaitMaximumCents' => $int('forfaitMaximumCents'),
            'forfaitLimitApplied' => $limit,
            'forfaitCents' => $int('forfaitCents'),
            'realExpensesCents' => $int('realExpensesCents'),
            'reintegratedReimbursementsCents' => $int('reintegratedReimbursementsCents'),
            'excludedAlreadyDeductedContributionCents' => $int('excludedAlreadyDeductedContributionCents'),
            'baseForfaitCents' => $int('baseForfaitCents'),
            'baseRealExpensesCents' => $int('baseRealExpensesCents'),
            'differenceCents' => $int('differenceCents'),
        ];
    }

    /** @return list<Rule> */
    private static function rulesFrom(mixed $value): array
    {
        if (!\is_array($value)) {
            throw new \InvalidArgumentException('Invalid rules.');
        }
        $rules = [];
        foreach ($value as $rule) {
            if (!\is_array($rule)) {
                throw new \InvalidArgumentException('Invalid rule.');
            }
            $code = $rule['code'] ?? null;
            $ruleValue = $rule['value'] ?? null;
            $version = $rule['version'] ?? null;
            $source = $rule['source'] ?? null;
            $effectiveFrom = $rule['effectiveFrom'] ?? null;
            if (!\is_string($code) || (null !== $ruleValue && !\is_int($ruleValue)) || !\is_string($version) || !\is_string($source) || !\is_string($effectiveFrom)) {
                throw new \InvalidArgumentException('Invalid rule.');
            }
            $rules[] = ['code' => $code, 'value' => $ruleValue, 'version' => $version, 'source' => $source, 'effectiveFrom' => $effectiveFrom];
        }

        return $rules;
    }

    /** @return Configuration|null */
    private static function configurationFrom(mixed $value): ?array
    {
        if (null === $value) {
            return null;
        }
        if (!\is_array($value) || !\is_int($value['year'] ?? null)) {
            throw new \InvalidArgumentException('Invalid configuration.');
        }
        $optional = static function (string $key) use ($value): ?string {
            $v = $value[$key] ?? null;
            if (null !== $v && !\is_string($v)) {
                throw new \InvalidArgumentException(\sprintf('Invalid configuration "%s".', $key));
            }

            return $v;
        };

        return ['year' => $value['year'], 'version' => $optional('version'), 'source' => $optional('source'), 'effectiveFrom' => $optional('effectiveFrom')];
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        if (!\is_array($value)) {
            throw new \InvalidArgumentException('Invalid list.');
        }
        $list = [];
        foreach ($value as $item) {
            if (!\is_string($item)) {
                throw new \InvalidArgumentException('Invalid list item.');
            }
            $list[] = $item;
        }

        return $list;
    }
}
