<?php

declare(strict_types=1);

use App\Expense\Domain\Entity\MealExpense;
use App\Expense\Domain\Entity\ParkingExpense;
use App\Expense\Domain\Entity\TollExpense;
use App\Expense\Domain\Entity\TravelExpense;
use App\Expense\Domain\Enum\VehicleType;
use App\Expense\Domain\ValueObject\ExpenseId;

$id = ExpenseId::fromString('00000000-0000-4000-8000-000000000001');
$date = new \DateTimeImmutable('2025-01-15');

// ── TravelExpense ──────────────────────────────────────────────────────────────

it('crée un TravelExpense avec une distance positive', function () use ($id, $date) {
    $e = new TravelExpense($id, 'person-1', $date, null, null, null, 10.0, 5);

    expect($e->distanceKm())->toBe(10.0);
});

it('rejette une distance nulle dans TravelExpense', function () use ($id, $date) {
    expect(fn () => new TravelExpense($id, 'person-1', $date, null, null, null, 0.0, 5))
        ->toThrow(\InvalidArgumentException::class);
});

it('rejette une distance négative dans TravelExpense', function () use ($id, $date) {
    expect(fn () => new TravelExpense($id, 'person-1', $date, null, null, null, -5.0, 5))
        ->toThrow(\InvalidArgumentException::class);
});

it('rejette une distance nulle via setDistanceKm', function () use ($id, $date) {
    $e = new TravelExpense($id, 'person-1', $date, null, null, null, 10.0, 5);

    expect(fn () => $e->setDistanceKm(0.0))->toThrow(\InvalidArgumentException::class);
});

// ── TollExpense ────────────────────────────────────────────────────────────────

it('crée un TollExpense avec un montant nul (gratuit)', function () use ($id, $date) {
    $e = new TollExpense($id, 'person-1', $date, null, 0.0, null, null);

    expect($e->amount())->toBe(0.0);
});

it('rejette un montant négatif dans TollExpense', function () use ($id, $date) {
    expect(fn () => new TollExpense($id, 'person-1', $date, null, -1.0, null, null))
        ->toThrow(\InvalidArgumentException::class);
});

it('rejette un montant négatif via setTollAmount', function () use ($id, $date) {
    $e = new TollExpense($id, 'person-1', $date, null, 5.0, null, null);

    expect(fn () => $e->setTollAmount(-1.0))->toThrow(\InvalidArgumentException::class);
});

// ── ParkingExpense ─────────────────────────────────────────────────────────────

it('rejette un montant négatif dans ParkingExpense', function () use ($id, $date) {
    expect(fn () => new ParkingExpense($id, 'person-1', $date, null, -0.01))
        ->toThrow(\InvalidArgumentException::class);
});

it('rejette un montant négatif via setParkingAmount', function () use ($id, $date) {
    $e = new ParkingExpense($id, 'person-1', $date, null, 3.0);

    expect(fn () => $e->setParkingAmount(-1.0))->toThrow(\InvalidArgumentException::class);
});

// ── MealExpense ────────────────────────────────────────────────────────────────

it('rejette un montant repas négatif', function () use ($id, $date) {
    expect(fn () => new MealExpense($id, 'person-1', $date, null, -1.0))
        ->toThrow(\InvalidArgumentException::class);
});

it('rejette une valeur repas domicile nulle ou négative', function () use ($id, $date) {
    expect(fn () => new MealExpense($id, 'person-1', $date, null, 10.0, 0.0))
        ->toThrow(\InvalidArgumentException::class);
});

it('rejette une contribution patronale négative', function () use ($id, $date) {
    expect(fn () => new MealExpense($id, 'person-1', $date, null, 10.0, 5.35, -1.0))
        ->toThrow(\InvalidArgumentException::class);
});

it('rejette un montant repas négatif via setMealAmount', function () use ($id, $date) {
    $e = new MealExpense($id, 'person-1', $date, null, 12.0);

    expect(fn () => $e->setMealAmount(-1.0))->toThrow(\InvalidArgumentException::class);
});