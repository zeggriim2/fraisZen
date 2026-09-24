<?php

declare(strict_types=1);

namespace App\Expense\Application\Query\GetReceiptDocument;

use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\Repository\ReceiptDocumentRepositoryInterface;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\SharedKernel\Application\Bus\QueryHandlerInterface;

final readonly class GetReceiptDocumentQueryHandler implements QueryHandlerInterface
{
    public function __construct(
        private ReceiptDocumentRepositoryInterface $repository,
        private ExpenseRepositoryInterface $expenses,
    ) {
    }

    /** @return array<string, mixed> */
    public function __invoke(GetReceiptDocumentQuery $query): array
    {
        $document = $this->repository->findDocument($query->documentId);
        if (null === $document || $document->personId() !== $query->personId) {
            throw new \DomainException('Facture introuvable.');
        }
        $lines = $this->repository->findLines($document->id());
        $lineIds = array_values(array_map(fn ($line): string => $line->id(), $lines));
        $matches = $this->repository->findMatchesForLines($lineIds);
        $byLine = [];
        foreach ($matches as $match) {
            $expense = $this->expenses->findById(ExpenseId::fromString($match->expenseId()));
            $byLine[$match->lineId()][] = [
                ...$match->toArray(),
                'expense' => null === $expense ? null : [
                    'id' => $expense->id()->value(),
                    'date' => $expense->date()->format('Y-m-d'),
                    'amount' => $expense->amount(),
                    'description' => $expense->description(),
                ],
            ];
        }
        foreach ($byLine as &$lineMatches) {
            usort($lineMatches, static fn (array $left, array $right): int => ('rejected' === $left['status']) <=> ('rejected' === $right['status']));
        }
        unset($lineMatches);

        return [...$document->toArray(), 'lines' => array_map(fn ($line) => [...$line->toArray(), 'matches' => $byLine[$line->id()] ?? []], $lines)];
    }
}
