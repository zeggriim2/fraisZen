<?php

declare(strict_types=1);

namespace App\Expense\Domain\Service;

/**
 * Barèmes kilométriques par année fiscale (déclaration N+1 sur revenus N).
 * Pour ajouter une nouvelle année : dupliquer le bloc de l'année précédente et ajuster les taux.
 *
 * Structure par type de véhicule :
 *  - car        : indexé par puissance fiscale (3–7 CV), tranches 5 000 / 20 000 km
 *  - motorcycle : indexé par groupe de puissance (1=1-2CV, 3=3-5CV, 6=>5CV), tranches 3 000 / 6 000 km
 *  - moped      : pas de puissance, tranches 3 000 / 6 000 km
 *  - electricMultiplier : majoration voiture électrique (uniquement 100 % électrique, pas hybride)
 */
final class BaremeKilometriqueProvider
{
    /** @var array<int, array{car: array, motorcycle: array, moped: array, electricMultiplier: float}> */
    private const BAREMES = [
        // Arrêté du 27 mars 2023 — inchangé pour 2023, 2024 et 2025
        2023 => [
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
        ],
        // 2024 — identique à 2023
        2024 => null,
        // 2025 — identique à 2023
        2025 => null,
    ];

    /**
     * Retourne le barème applicable pour une année fiscale donnée.
     * Si l'année n'a pas de barème propre (null), utilise le plus récent connu.
     * Si l'année est supérieure à toutes les années connues, utilise le dernier barème.
     *
     * @return array{car: array, motorcycle: array, moped: array, electricMultiplier: float}
     */
    public static function forYear(int $year): array
    {
        $knownYears = array_keys(self::BAREMES);
        sort($knownYears);

        // Trouver l'année de référence : la plus proche ≤ $year, avec barème non null
        $refYear = null;
        foreach ($knownYears as $y) {
            if ($y <= $year && self::BAREMES[$y] !== null) {
                $refYear = $y;
            }
        }

        // Si aucune année connue ≤ $year, utilise la première disponible
        if ($refYear === null) {
            foreach ($knownYears as $y) {
                if (self::BAREMES[$y] !== null) {
                    $refYear = $y;
                    break;
                }
            }
        }

        /** @var array{car: array, motorcycle: array, moped: array, electricMultiplier: float} */
        return self::BAREMES[$refYear];
    }
}
