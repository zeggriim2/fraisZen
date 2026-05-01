<?php

declare(strict_types=1);

namespace App\Admin\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'fiscal_config')]
class FiscalConfig
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private int $year;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private float $remoteWorkDailyAllowance;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private float $homeMealValue;

    public function __construct(int $year, float $remoteWorkDailyAllowance, float $homeMealValue = 5.35)
    {
        $this->year = $year;
        $this->remoteWorkDailyAllowance = $remoteWorkDailyAllowance;
        $this->homeMealValue = $homeMealValue;
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
        $this->remoteWorkDailyAllowance = $amount;
    }

    public function setHomeMealValue(float $amount): void
    {
        $this->homeMealValue = $amount;
    }

    public function toArray(): array
    {
        return [
            'year' => $this->year,
            'remoteWorkDailyAllowance' => $this->remoteWorkDailyAllowance(),
            'homeMealValue' => $this->homeMealValue(),
        ];
    }
}
