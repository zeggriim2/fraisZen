<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Admin\Domain\Entity\FiscalConfig;
use App\Expense\Domain\Entity\BaremeKilometrique;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ReferentialFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Valeurs officielles par année (source : migrations Version20260428000001 + Version20260429000001)
        foreach ([
            [2023, '2.50', '4.85'],
            [2024, '2.70', '5.35'],
            [2025, '2.70', '5.45'],
        ] as [$year, $allowance, $meal]) {
            $manager->persist(new FiscalConfig($year, $allowance, $meal));
        }

        // Arrêté du 27 mars 2023, reconduit sur 2023-2026 (source : migration Version20260506000001)
        $rates = [
            'car' => [
                3 => ['rate1' => 0.529, 'rate2' => 0.316, 'fixed2' => 1065, 'rate3' => 0.370],
                4 => ['rate1' => 0.606, 'rate2' => 0.340, 'fixed2' => 1330, 'rate3' => 0.407],
                5 => ['rate1' => 0.636, 'rate2' => 0.357, 'fixed2' => 1395, 'rate3' => 0.427],
                6 => ['rate1' => 0.665, 'rate2' => 0.374, 'fixed2' => 1457, 'rate3' => 0.447],
                7 => ['rate1' => 0.697, 'rate2' => 0.394, 'fixed2' => 1515, 'rate3' => 0.470],
            ],
            'motorcycle' => [
                1 => ['rate1' => 0.395, 'rate2' => 0.099, 'fixed2' => 891,  'rate3' => 0.248],
                3 => ['rate1' => 0.468, 'rate2' => 0.082, 'fixed2' => 1158, 'rate3' => 0.275],
                6 => ['rate1' => 0.606, 'rate2' => 0.079, 'fixed2' => 1583, 'rate3' => 0.343],
            ],
            'moped' => ['rate1' => 0.315, 'rate2' => 0.079, 'fixed2' => 711, 'rate3' => 0.198],
            'electricMultiplier' => 1.20,
        ];

        foreach ([2023, 2024, 2025, 2026] as $year) {
            $manager->persist(new BaremeKilometrique($year, $rates));
        }

        $manager->flush();
    }
}
