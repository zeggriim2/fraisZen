<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\AttachReceiptLine;

use App\Expense\Domain\Entity\ReceiptMatch;
use App\Expense\Domain\Entity\TollExpense;
use App\Expense\Domain\Enum\ReceiptMatchStatus;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\Repository\ReceiptDocumentRepositoryInterface;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;

final readonly class AttachReceiptLineCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private ReceiptDocumentRepositoryInterface $documents,
        private ExpenseRepositoryInterface $expenses,
    ) {
    }

    /** @return array<string, mixed> */
    public function __invoke(AttachReceiptLineCommand $command): array
    {
        $document = $this->documents->findDocument($command->documentId);
        $line = $this->documents->findLine($command->lineId);
        $expense = $this->expenses->findById(ExpenseId::fromString($command->expenseId));

        if (null === $document || $document->personId() !== $command->personId || null === $line || $line->documentId() !== $document->id()) {
            throw new \DomainException('Ligne de justificatif introuvable.');
        }

        if (!$expense instanceof TollExpense || $expense->personId() !== $command->personId) {
            throw new \DomainException('Dépense de péage introuvable.');
        }

        foreach ($this->documents->findMatchesForLines([$line->id()]) as $existingMatch) {
            if (ReceiptMatchStatus::Rejected !== $existingMatch->status()) {
                $existingMatch->reject();
                $this->documents->saveMatch($existingMatch);
            }
        }

        $match = new ReceiptMatch(
            $line->id(),
            $expense->id()->value(),
            100,
            ReceiptMatchStatus::Confirmed,
            ['rattachement manuel'],
        );
        $this->documents->saveMatch($match);
        $this->documents->flush();

        return $match->toArray();
    }
}
