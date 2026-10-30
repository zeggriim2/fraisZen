<?php

declare(strict_types=1);

namespace App\Admin\Application\Command\UpsertFiscalConfig;

/**
 * Upsert of the yearly fiscal configuration.
 *
 * The six tax comparison parameters (forfait 10 %) are optional as a group: either all of them are
 * provided and applied through FiscalConfig::defineTaxComparisonRules(), or none of them is provided
 * and the existing parameters of the year are left untouched. Amounts are expressed in cents and the
 * effective date as an ISO `Y-m-d` string.
 */
final readonly class UpsertFiscalConfigCommand
{
    public function __construct(
        public int $year,
        public float $remoteWorkDailyAllowance,
        public ?float $homeMealValue,
        public ?int $forfaitRatePercent = null,
        public ?int $forfaitMinimumCents = null,
        public ?int $forfaitMaximumCents = null,
        public ?string $version = null,
        public ?string $source = null,
        public ?string $effectiveFrom = null,
    ) {
    }

    /** @return array<string, int|string|null> */
    public function taxComparisonFields(): array
    {
        return [
            'forfaitRatePercent' => $this->forfaitRatePercent,
            'forfaitMinimumCents' => $this->forfaitMinimumCents,
            'forfaitMaximumCents' => $this->forfaitMaximumCents,
            'version' => $this->version,
            'source' => $this->source,
            'effectiveFrom' => $this->effectiveFrom,
        ];
    }

    public function hasTaxComparisonFields(): bool
    {
        foreach ($this->taxComparisonFields() as $value) {
            if (null !== $value) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function missingTaxComparisonFields(): array
    {
        return array_keys(array_filter($this->taxComparisonFields(), static fn (int|string|null $value): bool => null === $value));
    }
}
