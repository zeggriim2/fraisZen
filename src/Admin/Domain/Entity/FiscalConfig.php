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

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $homeMealValue;

    public function __construct(int $year, string $remoteWorkDailyAllowance, string $homeMealValue = '5.35')
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
            'year' => $this->year,
            'remoteWorkDailyAllowance' => $this->remoteWorkDailyAllowance(),
            'homeMealValue' => $this->homeMealValue(),
        ];
    }
}
