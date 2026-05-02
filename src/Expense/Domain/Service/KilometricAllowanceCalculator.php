<?php

declare(strict_types=1);

namespace App\Expense\Domain\Service;

final class KilometricAllowanceCalculator
{
    /**
     * @param array<array-key, array{distanceKm: float, vehiclePower: int|null, vehicleType?: string, isElectric?: bool, ...}> $trips
     */
    public function calculateAnnualDeduction(array $trips, int $year): float
    {
        $bareme = BaremeKilometriqueProvider::forYear($year);

        $buckets = [];
        foreach ($trips as $trip) {
            $type = $trip['vehicleType'] ?? 'car';
            $power = (int) ($trip['vehiclePower'] ?? 0);
            $electric = (bool) ($trip['isElectric'] ?? false);
            $key = $type.'|'.$power.'|'.($electric ? '1' : '0');
            if (!isset($buckets[$key])) {
                $buckets[$key] = ['type' => $type, 'power' => $power, 'electric' => $electric, 'km' => 0.0];
            }
            $buckets[$key]['km'] += (float) $trip['distanceKm'];
        }

        $total = 0.0;
        foreach ($buckets as $b) {
            $total += $this->calculateBucket($bareme, $b['type'], $b['power'], $b['km'], $b['electric']);
        }

        return round($total, 2);
    }

    /**
     * @param array{car: array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}>, motorcycle: array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}>, moped: array{rate1: float, rate2: float, fixed2: int, rate3: float}, electricMultiplier: float} $bareme
     */
    private function calculateBucket(array $bareme, string $type, int $power, float $km, bool $electric): float
    {
        $amount = match ($type) {
            'motorcycle' => $this->forMotorcycle($bareme['motorcycle'], $power, $km),
            'moped' => $this->applyTranches($bareme['moped'], $km, 3000, 6000),
            default => $this->forCar($bareme['car'], $power, $km),
        };

        return ($electric && 'car' === $type) ? $amount * $bareme['electricMultiplier'] : $amount;
    }

    /**
     * @param array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}> $carBareme
     */
    private function forCar(array $carBareme, int $power, float $km): float
    {
        $cv = min(max($power, 3), 7);

        return $this->applyTranches($carBareme[$cv], $km, 5000, 20000);
    }

    /**
     * @param array<int, array{rate1: float, rate2: float, fixed2: int, rate3: float}> $motoBareme
     */
    private function forMotorcycle(array $motoBareme, int $power, float $km): float
    {
        $group = match (true) {
            $power <= 2 => 1,
            $power <= 5 => 3,
            default => 6,
        };

        return $this->applyTranches($motoBareme[$group], $km, 3000, 6000);
    }

    /**
     * @param array{rate1: float, rate2: float, fixed2: int, rate3: float} $b
     */
    private function applyTranches(array $b, float $km, int $tranche1, int $tranche2): float
    {
        return match (true) {
            $km <= $tranche1 => $km * $b['rate1'],
            $km <= $tranche2 => ($km * $b['rate2']) + (float) $b['fixed2'],
            default => $km * $b['rate3'],
        };
    }

    public function calculateForPowerAndDistance(int $vehiclePower, float $totalKm, int $year = 2025): float
    {
        $bareme = BaremeKilometriqueProvider::forYear($year);

        return $this->forCar($bareme['car'], $vehiclePower, $totalKm);
    }
}
