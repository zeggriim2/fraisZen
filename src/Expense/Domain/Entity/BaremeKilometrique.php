<?php

declare(strict_types=1);

namespace App\Expense\Domain\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bareme_kilometrique')]
class BaremeKilometrique
{
    #[ORM\Id]
    #[ORM\Column(type: Types::INTEGER)]
    private int $year;

    /**
     * @var array{car: array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}>, motorcycle: array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}>, moped: array{rate1: float, rate2: float, fixed2: int, rate3: float}, electricMultiplier: float}
     */
    #[ORM\Column(type: Types::JSON)]
    private array $rates;

    /**
     * @param array{car: array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}>, motorcycle: array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}>, moped: array{rate1: float, rate2: float, fixed2: int, rate3: float}, electricMultiplier: float} $rates
     */
    public function __construct(int $year, array $rates)
    {
        $this->year = $year;
        $this->rates = $rates;
    }

    public function year(): int
    {
        return $this->year;
    }

    /**
     * @return array{car: array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}>, motorcycle: array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}>, moped: array{rate1: float, rate2: float, fixed2: int, rate3: float}, electricMultiplier: float}
     */
    public function rates(): array
    {
        return $this->rates;
    }

    /**
     * @param array{car: array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}>, motorcycle: array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}>, moped: array{rate1: float, rate2: float, fixed2: int, rate3: float}, electricMultiplier: float} $rates
     */
    public function setRates(array $rates): void
    {
        $this->rates = $rates;
    }

    /**
     * @return array{year: int, rates: array{car: array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}>, motorcycle: array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}>, moped: array{rate1: float, rate2: float, fixed2: int, rate3: float}, electricMultiplier: float}}
     */
    public function toArray(): array
    {
        return [
            'year' => $this->year,
            'rates' => $this->rates,
        ];
    }
}
