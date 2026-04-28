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

    public function __construct(int $year, float $remoteWorkDailyAllowance)
    {
        $this->year = $year;
        $this->remoteWorkDailyAllowance = $remoteWorkDailyAllowance;
    }

    public function year(): int { return $this->year; }
    public function remoteWorkDailyAllowance(): float { return (float) $this->remoteWorkDailyAllowance; }

    public function setRemoteWorkDailyAllowance(float $amount): void
    {
        $this->remoteWorkDailyAllowance = $amount;
    }

    public function toArray(): array
    {
        return [
            'year'                    => $this->year,
            'remoteWorkDailyAllowance' => $this->remoteWorkDailyAllowance(),
        ];
    }
}