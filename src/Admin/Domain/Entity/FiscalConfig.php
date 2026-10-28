<?php

declare(strict_types=1);

namespace App\Admin\Domain\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'fiscal_config')]
class FiscalConfig
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    private int $year;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $remoteWorkDailyAllowance;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, options: ['default' => '5.35'])]
    private string $homeMealValue;

    #[ORM\Column(name: 'forfait_rate_percent', type: Types::INTEGER, nullable: true)]
    private ?int $forfaitRatePercent;

    #[ORM\Column(name: 'forfait_minimum_cents', type: Types::INTEGER, nullable: true)]
    private ?int $forfaitMinimumCents;

    #[ORM\Column(name: 'forfait_maximum_cents', type: Types::INTEGER, nullable: true)]
    private ?int $forfaitMaximumCents;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $version;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $source;

    #[ORM\Column(name: 'effective_from', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $effectiveFrom;

    /**
     * Legacy positional order (year, remote work allowance, home meal value) is preserved;
     * the versioned tax comparison parameters are appended and optional.
     */
    public function __construct(
        int $year,
        string $remoteWorkDailyAllowance,
        string $homeMealValue = '5.35',
        ?int $forfaitRatePercent = null,
        ?int $forfaitMinimumCents = null,
        ?int $forfaitMaximumCents = null,
        ?string $version = null,
        ?string $source = null,
        ?\DateTimeImmutable $effectiveFrom = null,
    ) {
        self::assertComparisonRules($forfaitRatePercent, $forfaitMinimumCents, $forfaitMaximumCents, $version, $source);
        $this->year = $year;
        $this->remoteWorkDailyAllowance = $remoteWorkDailyAllowance;
        $this->homeMealValue = $homeMealValue;
        $this->forfaitRatePercent = $forfaitRatePercent;
        $this->forfaitMinimumCents = $forfaitMinimumCents;
        $this->forfaitMaximumCents = $forfaitMaximumCents;
        $this->version = $version;
        $this->source = $source;
        $this->effectiveFrom = $effectiveFrom;
    }

    public function defineTaxComparisonRules(
        int $forfaitRatePercent,
        int $forfaitMinimumCents,
        int $forfaitMaximumCents,
        string $version,
        string $source,
        \DateTimeImmutable $effectiveFrom,
    ): void {
        self::assertComparisonRules($forfaitRatePercent, $forfaitMinimumCents, $forfaitMaximumCents, $version, $source);
        $this->forfaitRatePercent = $forfaitRatePercent;
        $this->forfaitMinimumCents = $forfaitMinimumCents;
        $this->forfaitMaximumCents = $forfaitMaximumCents;
        $this->version = $version;
        $this->source = $source;
        $this->effectiveFrom = $effectiveFrom;
    }

    /**
     * Names of the tax comparison parameters that are not configured for this year.
     *
     * @return list<string>
     */
    public function missingTaxComparisonFields(): array
    {
        $fields = [
            'forfait_rate_percent' => $this->forfaitRatePercent,
            'forfait_minimum_cents' => $this->forfaitMinimumCents,
            'forfait_maximum_cents' => $this->forfaitMaximumCents,
            'version' => $this->version,
            'source' => $this->source,
            'effective_from' => $this->effectiveFrom,
        ];

        return array_keys(array_filter($fields, static fn (mixed $value): bool => null === $value));
    }

    public function isComplete(): bool
    {
        return [] === $this->missingTaxComparisonFields();
    }

    public function forfaitRatePercent(): ?int
    {
        return $this->forfaitRatePercent;
    }

    public function forfaitMinimumCents(): ?int
    {
        return $this->forfaitMinimumCents;
    }

    public function forfaitMaximumCents(): ?int
    {
        return $this->forfaitMaximumCents;
    }

    public function version(): ?string
    {
        return $this->version;
    }

    public function source(): ?string
    {
        return $this->source;
    }

    public function effectiveFrom(): ?\DateTimeImmutable
    {
        return $this->effectiveFrom;
    }

    /** @return array{year: int, version: ?string, source: ?string, effectiveFrom: ?string} */
    public function metadata(): array
    {
        return [
            'year' => $this->year,
            'version' => $this->version,
            'source' => $this->source,
            'effectiveFrom' => $this->effectiveFrom?->format('Y-m-d'),
        ];
    }

    private static function assertComparisonRules(?int $rate, ?int $minimum, ?int $maximum, ?string $version, ?string $source): void
    {
        if (null !== $rate && ($rate <= 0 || $rate > 100)) {
            throw new \InvalidArgumentException('Forfait rate must be between 1 and 100 percent.');
        }
        if ((null !== $minimum && $minimum < 0) || (null !== $maximum && $maximum < 0)) {
            throw new \InvalidArgumentException('Forfait minimum and maximum cannot be negative.');
        }
        if (null !== $minimum && null !== $maximum && $minimum > $maximum) {
            throw new \InvalidArgumentException('Forfait minimum cannot exceed the maximum.');
        }
        if ((null !== $version && '' === trim($version)) || (null !== $source && '' === trim($source))) {
            throw new \InvalidArgumentException('Fiscal configuration version and source cannot be blank.');
        }
    }

    public function year(): int
    {
        return $this->year;
    }

    public function remoteWorkDailyAllowance(): float
    {
        return (float) $this->remoteWorkDailyAllowance;
    }

    public function homeMealValue(): float
    {
        return (float) $this->homeMealValue;
    }

    public function setRemoteWorkDailyAllowance(float $amount): void
    {
        $this->remoteWorkDailyAllowance = (string) $amount;
    }

    public function setHomeMealValue(float $amount): void
    {
        $this->homeMealValue = (string) $amount;
    }

    /** @return array{year: int, remoteWorkDailyAllowance: float, homeMealValue: float} */
    public function toArray(): array
    {
        return [
            'year' => $this->year(),
            'remoteWorkDailyAllowance' => $this->remoteWorkDailyAllowance(),
            'homeMealValue' => $this->homeMealValue(),
        ];
    }
}
