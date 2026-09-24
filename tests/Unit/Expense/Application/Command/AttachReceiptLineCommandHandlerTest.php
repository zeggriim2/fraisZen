<?php

declare(strict_types=1);

use App\Expense\Application\Command\AttachReceiptLine\AttachReceiptLineCommand;
use App\Expense\Application\Command\AttachReceiptLine\AttachReceiptLineCommandHandler;
use App\Expense\Domain\Entity\ReceiptDocument;
use App\Expense\Domain\Entity\ReceiptLine;
use App\Expense\Domain\Entity\ReceiptMatch;
use App\Expense\Domain\Entity\TollExpense;
use App\Expense\Domain\Enum\ReceiptMatchStatus;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\Repository\ReceiptDocumentRepositoryInterface;
use App\Expense\Domain\ValueObject\ExpenseId;

it('remplace le rapprochement existant et ne flush qu’une fois', function (): void {
    $personId = 'b0753f95-5ad1-4529-9ac8-35a89ceaa524';
    $document = new ReceiptDocument($personId, 'facture.pdf', 'application/pdf', str_repeat('a', 64));
    $line = new ReceiptLine($document->id(), 1, new DateTimeImmutable('2026-08-04'), 'Paris', 'Lyon', 6.17, 7.40, 42.0, 'raw');
    $expense = new TollExpense(ExpenseId::generate(), $personId, new DateTimeImmutable('2026-08-04'), null, 7.40, 'Paris', 'Lyon');
    $existing = new ReceiptMatch($line->id(), ExpenseId::generate()->value(), 80, ReceiptMatchStatus::Suggested, ['montant exact']);

    $documents = $this->createMock(ReceiptDocumentRepositoryInterface::class);
    $documents->method('findDocument')->willReturn($document);
    $documents->method('findLine')->willReturn($line);
    $documents->method('findMatchesForLines')->willReturn([$existing]);
    $documents->expects($this->exactly(2))->method('saveMatch');
    $documents->expects($this->once())->method('flush');

    $expenses = $this->createMock(ExpenseRepositoryInterface::class);
    $expenses->method('findById')->willReturn($expense);

    $result = (new AttachReceiptLineCommandHandler($documents, $expenses))(new AttachReceiptLineCommand(
        $document->id(),
        $line->id(),
        $expense->id()->value(),
        $personId,
    ));

    expect($existing->status())->toBe(ReceiptMatchStatus::Rejected)
        ->and($result['status'])->toBe('confirmed')
        ->and($result['confidence'])->toBe(100)
        ->and($result['reasons'])->toBe(['rattachement manuel']);
});
