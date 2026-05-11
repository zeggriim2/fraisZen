<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminBareme\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class TrancheTauxInput
{
    #[Assert\Positive]
    public float $rate1 = 0.0;

    #[Assert\Positive]
    public float $rate2 = 0.0;

    #[Assert\Positive]
    public int $fixed2 = 0;

    #[Assert\Positive]
    public float $rate3 = 0.0;

    /**
     * @return array{rate1: float, rate2: float, fixed2: int, rate3: float}
     */
    public function toArray(): array
    {
        return [
            'rate1' => $this->rate1,
            'rate2' => $this->rate2,
            'fixed2' => $this->fixed2,
            'rate3' => $this->rate3,
        ];
    }
}
