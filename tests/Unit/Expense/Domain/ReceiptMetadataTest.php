<?php

declare(strict_types=1);

use App\Expense\Domain\Entity\ParkingExpense;
use App\Expense\Domain\Entity\TollExpense;
use App\Expense\Domain\ValueObject\ExpenseId;

it('attache les métadonnées de justificatif à n’importe quelle dépense', function () {
    $expense = new TollExpense(
        ExpenseId::fromString('00000000-0000-4000-8000-000000000301'),
        'person-1',
        new DateTimeImmutable('2025-02-10'),
        'Péage client',
        18.40,
        'Paris',
        'Lille',
    );

    $expense->setReceipt('ticket-peage.png', 'image/png');

    expect($expense->receiptFilename())->toBe('ticket-peage.png');
    expect($expense->receiptMimeType())->toBe('image/png');
    expect($expense->toArray())
        ->toHaveKey('receiptFilename', 'ticket-peage.png')
        ->toHaveKey('receiptMimeType', 'image/png');
});

it('supprime les métadonnées de justificatif en conservant la dépense', function () {
    $expense = new ParkingExpense(
        ExpenseId::fromString('00000000-0000-4000-8000-000000000302'),
        'person-1',
        new DateTimeImmutable('2025-03-12'),
        null,
        7.20,
        'Gare',
    );

    $expense->setReceipt('parking.pdf', 'application/pdf');
    $expense->setReceipt(null, null);

    expect($expense->receiptFilename())->toBeNull();
    expect($expense->receiptMimeType())->toBeNull();
    expect($expense->toArray())
        ->toHaveKey('receiptFilename', null)
        ->toHaveKey('receiptMimeType', null);
});

it('conserve les champs spécifiques du parking après généralisation des justificatifs', function () {
    $expense = new ParkingExpense(
        ExpenseId::fromString('00000000-0000-4000-8000-000000000303'),
        'person-1',
        new DateTimeImmutable('2025-04-01'),
        'Parking mission',
        9.99,
        'Centre-ville',
    );

    $expense->setReceipt('parking.webp', 'image/webp');

    expect($expense->toArray())
        ->toHaveKey('parkingAmount', 9.99)
        ->toHaveKey('location', 'Centre-ville')
        ->toHaveKey('receiptFilename', 'parking.webp');
});
