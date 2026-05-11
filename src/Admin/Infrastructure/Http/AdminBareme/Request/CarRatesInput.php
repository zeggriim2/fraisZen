<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminBareme\Request;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

final class CarRatesInput
{
    #[SerializedName('3')]
    #[Assert\NotNull(message: '3 CV requis.')]
    #[Assert\Valid]
    public TrancheTauxInput $cv3;

    #[SerializedName('4')]
    #[Assert\NotNull(message: '4 CV requis.')]
    #[Assert\Valid]
    public TrancheTauxInput $cv4;

    #[SerializedName('5')]
    #[Assert\NotNull(message: '5 CV requis.')]
    #[Assert\Valid]
    public TrancheTauxInput $cv5;

    #[SerializedName('6')]
    #[Assert\NotNull(message: '6 CV requis.')]
    #[Assert\Valid]
    public TrancheTauxInput $cv6;

    #[SerializedName('7')]
    #[Assert\NotNull(message: '7 CV requis.')]
    #[Assert\Valid]
    public TrancheTauxInput $cv7;

    /**
     * @return array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}>
     */
    public function toArray(): array
    {
        return [
            3 => $this->cv3->toArray(),
            4 => $this->cv4->toArray(),
            5 => $this->cv5->toArray(),
            6 => $this->cv6->toArray(),
            7 => $this->cv7->toArray(),
        ];
    }
}
