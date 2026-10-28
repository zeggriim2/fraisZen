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

    public function __construct(int $year, string $remoteWorkDailyAllowance, string $homeMealValue = '5.35', ?int $forfaitRatePercent = null, ?int $forfaitMinimumCents = null, ?int $forfaitMaximumCents = null, ?string $version = null, ?string $source = null, ?\DateTimeImmutable $effectiveFrom = null)
    {
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

    public function isComplete(): bool
    {
        return null !== $this->forfaitRatePercent && null !== $this->forfaitMinimumCents && null !== $this->forfaitMaximumCents && null !== $this->version && null !== $this->source && null !== $this->effectiveFrom;
    }

    public function forfaitRatePercent(): int
    {
        return $this->forfaitRatePercent ?? 0;
    }

    public function forfaitMinimumCents(): int
    {
        return $this->forfaitMinimumCents ?? 0;
    }

    public function forfaitMaximumCents(): int
    {
        return $this->forfaitMaximumCents ?? 0;
    }

    /** @return array{year:int, version:?string, source:?string, effectiveFrom:?string} */
    public function metadata(): array
    {
        return ['year' => $this->year, 'version' => $this->version, 'source' => $this->source, 'effectiveFrom' => $this->effectiveFrom?->format('Y-m-d')];
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
