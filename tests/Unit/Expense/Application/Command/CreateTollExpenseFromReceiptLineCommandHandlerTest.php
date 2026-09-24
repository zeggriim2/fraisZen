<?php

declare(strict_types=1);

use App\Expense\Application\Command\CreateTollExpenseFromReceiptLine\CreateTollExpenseFromReceiptLineCommand;
use App\Expense\Application\Command\CreateTollExpenseFromReceiptLine\CreateTollExpenseFromReceiptLineCommandHandler;
use App\Expense\Application\Persistence\ReceiptExpenseUnitOfWorkInterface;
use App\Expense\Domain\Entity\ReceiptDocument;
use App\Expense\Domain\Entity\ReceiptLine;
use App\Expense\Domain\Entity\ReceiptMatch;
use App\Expense\Domain\Entity\TollExpense;
use App\Expense\Domain\Repository\ReceiptDocumentRepositoryInterface;

it('crée et rattache une dépense de péage avec un seul flush', function (): void {
    $personId = 'b0753f95-5ad1-4529-9ac8-35a89ceaa524';
    $document = new ReceiptDocument($personId, 'facture.pdf', 'application/pdf', str_repeat('b', 64));
    $line = new ReceiptLine($document->id(), 1, new DateTimeImmutable('2026-08-16'), 'A6 Paris', 'A7 Lyon', 6.17, 7.40, 42.0, 'raw');

    $documents = $this->createMock(ReceiptDocumentRepositoryInterface::class);
    $documents->method('findDocument')->willReturn($document);
    $documents->method('findLine')->willReturn($line);
    $documents->method('findMatchesForLines')->willReturn([]);

    $unitOfWork = $this->createMock(ReceiptExpenseUnitOfWorkInterface::class);
    $unitOfWork->expects($this->once())->method('persist')->with(
        $this->callback(fn (TollExpense $expense): bool => $expense->personId() === $personId
            && $expense->date()->format('Y-m-d') === '2026-08-16'
            && 7.40 === $expense->amount()
            && 'A6 Paris' === $expense->departure()
            && 'A7 Lyon' === $expense->arrival()),
        $this->callback(fn (ReceiptMatch $match): bool => $match->lineId() === $line->id()
            && 'confirmed' === $match->status()->value),
    );
    $unitOfWork->expects($this->once())->method('flush');

    $result = (new CreateTollExpenseFromReceiptLineCommandHandler($documents, $unitOfWork))(
        new CreateTollExpenseFromReceiptLineCommand($document->id(), $line->id(), $personId),
    );

    expect($result)->toHaveKeys(['expenseId', 'matchId']);
});
