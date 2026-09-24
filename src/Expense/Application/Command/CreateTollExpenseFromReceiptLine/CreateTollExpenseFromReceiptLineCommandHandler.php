<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\CreateTollExpenseFromReceiptLine;

use App\Expense\Application\Persistence\ReceiptExpenseUnitOfWorkInterface;
use App\Expense\Domain\Entity\ReceiptMatch;
use App\Expense\Domain\Entity\TollExpense;
use App\Expense\Domain\Enum\ReceiptMatchStatus;
use App\Expense\Domain\Repository\ReceiptDocumentRepositoryInterface;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;

final readonly class CreateTollExpenseFromReceiptLineCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private ReceiptDocumentRepositoryInterface $documents,
        private ReceiptExpenseUnitOfWorkInterface $unitOfWork,
    ) {
    }

    /** @return array{expenseId: string, matchId: string} */
    public function __invoke(CreateTollExpenseFromReceiptLineCommand $command): array
    {
        $document = $this->documents->findDocument($command->documentId);
        $line = $this->documents->findLine($command->lineId);

        if (null === $document || $document->personId() !== $command->personId || null === $line || $line->documentId() !== $document->id()) {
            throw new \DomainException('Ligne de justificatif introuvable.');
        }

        foreach ($this->documents->findMatchesForLines([$line->id()]) as $existingMatch) {
            if (ReceiptMatchStatus::Rejected !== $existingMatch->status()) {
                $existingMatch->reject();
            }
        }

        $description = trim(sprintf('Péage %s → %s', $line->departure() ?? '', $line->arrival() ?? ''), ' →');

        $expense = new TollExpense(
            ExpenseId::generate(),
            $command->personId,
            $line->date(),
            '' === $description ? 'Péage importé depuis un justificatif' : $description,
            $line->amountTtc(),
            $line->departure(),
            $line->arrival(),
        );

        $match = new ReceiptMatch(
            $line->id(),
            $expense->id()->value(),
            100,
            ReceiptMatchStatus::Confirmed,
            ['dépense créée depuis le justificatif'],
        );

        $this->unitOfWork->persist($expense, $match);
        $this->unitOfWork->flush();

        return ['expenseId' => $expense->id()->value(), 'matchId' => $match->id()];
    }
}
