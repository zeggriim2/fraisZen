<?php

declare(strict_types=1);

namespace App\Admin\Application\ReadModel;

use App\Expense\Domain\Entity\BaremeKilometrique;

final readonly class BaremeKilometriqueReadModel
{
    public function __construct(
        public int $year,
        public BaremeRatesReadModel $rates,
    ) {
    }

    public static function fromEntity(BaremeKilometrique $entity): self
    {
        return new self(
            year: $entity->year(),
            rates: BaremeRatesReadModel::fromArray($entity->rates()),
        );
    }
}
