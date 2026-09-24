<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\ReviewReceiptMatch;

use App\Expense\Domain\Enum\ReceiptMatchStatus;
use App\Expense\Domain\Repository\ReceiptDocumentRepositoryInterface;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;

final readonly class ReviewReceiptMatchCommandHandler implements CommandHandlerInterface
{
    public function __construct(private ReceiptDocumentRepositoryInterface $repository)
    {
    }

    /** @return array<string, mixed> */
    public function __invoke(ReviewReceiptMatchCommand $command): array
    {
        $document = $this->repository->findDocument($command->documentId);
        $match = $this->repository->findMatch($command->matchId);
        if (null === $document || $document->personId() !== $command->personId || null === $match) {
            throw new \DomainException('Rapprochement introuvable.');
        }$lineIds = array_map(fn ($l) => $l->id(), $this->repository->findLines($document->id()));
        if (!in_array($match->lineId(), $lineIds, true)) {
            throw new \DomainException('Rapprochement introuvable.');
        }
        if ($command->confirmed) {
            foreach ($this->repository->findMatchesForLines([$match->lineId()]) as $otherMatch) {
                if ($otherMatch->id() !== $match->id() && ReceiptMatchStatus::Rejected !== $otherMatch->status()) {
                    $otherMatch->reject();
                    $this->repository->saveMatch($otherMatch);
                }
            }
            $match->confirm();
        } else {
            $match->reject();
        }
        $this->repository->saveMatch($match);
        $this->repository->flush();

        return $match->toArray();
    }
}
