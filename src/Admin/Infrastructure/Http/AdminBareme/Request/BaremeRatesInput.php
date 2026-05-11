<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminBareme\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class BaremeRatesInput
{
    #[Assert\NotNull(message: 'Le champ car est requis.')]
    #[Assert\Valid]
    public CarRatesInput $car;

    #[Assert\NotNull(message: 'Le champ motorcycle est requis.')]
    #[Assert\Valid]
    public MotorcycleRatesInput $motorcycle;

    #[Assert\NotNull(message: 'Le champ moped est requis.')]
    #[Assert\Valid]
    public TrancheTauxInput $moped;

    #[Assert\GreaterThanOrEqual(value: 1, message: 'electricMultiplier doit être ≥ 1.')]
    public float $electricMultiplier = 0.0;

    /**
     * @return array{car: array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}>, motorcycle: array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}>, moped: array{rate1: float, rate2: float, fixed2: int, rate3: float}, electricMultiplier: float}
     */
    public function toRatesArray(): array
    {
        return [
            'car' => $this->car->toArray(),
            'motorcycle' => $this->motorcycle->toArray(),
            'moped' => $this->moped->toArray(),
            'electricMultiplier' => $this->electricMultiplier,
        ];
    }
}
