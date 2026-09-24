<?php

declare(strict_types=1);

use App\Expense\Domain\Entity\ParkingExpense;
use App\Expense\Domain\Entity\TollExpense;
use App\Expense\Domain\Entity\MealExpense;
use App\Expense\Domain\Entity\RemoteWorkExpense;
use App\Expense\Domain\Service\ReceiptEligibility;
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
    $uploadedAt = new DateTimeImmutable('2026-09-23T16:00:00+00:00');
    $expense->setReceiptMetadata('abc123', [
        'status' => 'completed',
        'merchant' => 'Autoroutes du Nord',
        'date' => '2025-02-10',
        'amount' => 18.40,
        'rawText' => 'Autoroutes du Nord 18,40 EUR',
    ], $uploadedAt);

    expect($expense->receiptFilename())->toBe('ticket-peage.png');
    expect($expense->receiptMimeType())->toBe('image/png');
    expect($expense->receiptSha256())->toBe('abc123');
    expect($expense->receiptOcrData())->toHaveKey('merchant', 'Autoroutes du Nord');
    expect($expense->receiptUploadedAt())->toBe($uploadedAt);
    expect($expense->toArray())
        ->toHaveKey('receiptFilename', 'ticket-peage.png')
        ->toHaveKey('receiptMimeType', 'image/png')
        ->toHaveKey('receiptUploadedAt', '2026-09-23T16:00:00+00:00');
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
    $expense->setReceiptMetadata('def456', ['status' => 'empty'], new DateTimeImmutable());
    $expense->setReceipt(null, null);
    $expense->setReceiptMetadata(null, null, null);

    expect($expense->receiptFilename())->toBeNull();
    expect($expense->receiptMimeType())->toBeNull();
    expect($expense->receiptSha256())->toBeNull();
    expect($expense->receiptOcrData())->toBeNull();
    expect($expense->receiptUploadedAt())->toBeNull();
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

it('limite les justificatifs aux péages, parkings et repas sans justificatif', function () {
    $id = fn (int $suffix) => ExpenseId::fromString(sprintf('00000000-0000-4000-8000-%012d', $suffix));
    $date = new DateTimeImmutable('2026-05-12');
    $toll = new TollExpense($id(401), 'person-1', $date, null, 4.5, null, null);
    $parking = new ParkingExpense($id(402), 'person-1', $date, null, 8.0);
    $mealWithoutReceipt = new MealExpense($id(403), 'person-1', $date, null, 12.0, 5.35, 0.0, true);
    $mealWithReceipt = new MealExpense($id(404), 'person-1', $date, null, 12.0, 5.35, 0.0, false);
    $remoteWork = new RemoteWorkExpense($id(405), 'person-1', $date, null);

    expect(ReceiptEligibility::supports($toll))->toBeTrue()
        ->and(ReceiptEligibility::supports($parking))->toBeTrue()
        ->and(ReceiptEligibility::supports($mealWithoutReceipt))->toBeTrue()
        ->and(ReceiptEligibility::supports($mealWithReceipt))->toBeFalse()
        ->and(ReceiptEligibility::supports($remoteWork))->toBeFalse();
});
