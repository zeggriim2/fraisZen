<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminBareme\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class UpsertBaremeRequest
{
    #[Assert\NotNull(message: 'Le champ rates est requis.')]
    #[Assert\Valid]
    public BaremeRatesInput $rates;
}
