<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminBareme\Request;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class MotorcycleRatesInput
{
    #[SerializedName('1')]
    #[Assert\NotNull(message: 'Groupe 1-2 CV requis.')]
    #[Assert\Valid]
    public ?TrancheTauxInput $group1 = null;

    #[SerializedName('3')]
    #[Assert\NotNull(message: 'Groupe 3-5 CV requis.')]
    #[Assert\Valid]
    public ?TrancheTauxInput $group3 = null;

    #[SerializedName('6')]
    #[Assert\NotNull(message: 'Groupe +5 CV requis.')]
    #[Assert\Valid]
    public ?TrancheTauxInput $group6 = null;

    /**
     * @return array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}>
     */
    public function toArray(): array
    {
        assert(null !== $this->group1 && null !== $this->group3 && null !== $this->group6);

        return [
            1 => $this->group1->toArray(),
            3 => $this->group3->toArray(),
            6 => $this->group6->toArray(),
        ];
    }
}
