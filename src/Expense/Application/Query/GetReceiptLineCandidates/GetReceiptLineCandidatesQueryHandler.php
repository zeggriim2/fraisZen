<?php

declare(strict_types=1);

namespace App\Expense\Application\Query\GetReceiptLineCandidates;

use App\Expense\Application\Receipt\ReceiptMatcher;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\Repository\ReceiptDocumentRepositoryInterface;
use App\SharedKernel\Application\Bus\QueryHandlerInterface;

final readonly class GetReceiptLineCandidatesQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private ReceiptDocumentRepositoryInterface $documents,
        private ExpenseRepositoryInterface $expenses,
        private ReceiptMatcher $matcher,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function __invoke(GetReceiptLineCandidatesQuery $query): array
    {
        $document = $this->documents->findDocument($query->documentId);
        $line = $this->documents->findLine($query->lineId);

        if (null === $document || $document->personId() !== $query->personId || null === $line || $line->documentId() !== $document->id()) {
            throw new \DomainException('Ligne de justificatif introuvable.');
        }

        $expenses = $this->expenses->findByPersonAndPeriod(
            $query->personId,
            $line->date()->modify('-31 days'),
            $line->date()->modify('+31 days'),
        );

        return array_map(static function (array $candidate): array {
            $expense = $candidate['expense'];

            return [
                'id' => $expense->id()->value(),
                'date' => $expense->date()->format('Y-m-d'),
                'amount' => $expense->amount(),
                'description' => $expense->description(),
                'departure' => $expense->departure(),
                'arrival' => $expense->arrival(),
                'score' => $candidate['score'],
                'reasons' => $candidate['reasons'],
            ];
        }, $this->matcher->rankCandidates($line, $expenses));
    }
}
