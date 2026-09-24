<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\UploadReceiptDocument;

use App\Expense\Application\Receipt\ReceiptDocumentParserInterface;
use App\Expense\Application\Receipt\ReceiptMatcher;
use App\Expense\Application\Receipt\ReceiptStorageInterface;
use App\Expense\Domain\Entity\ReceiptDocument;
use App\Expense\Domain\Entity\ReceiptLine;
use App\Expense\Domain\Entity\ReceiptMatch;
use App\Expense\Domain\Enum\ReceiptMatchStatus;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\Repository\ReceiptDocumentRepositoryInterface;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;

final readonly class UploadReceiptDocumentCommandHandler implements CommandHandlerInterface
{
    private const MAX_SIZE = 20 * 1024 * 1024;
    private const EXTENSIONS = ['application/pdf' => 'pdf', 'application/x-pdf' => 'pdf'];

    public function __construct(private ReceiptDocumentRepositoryInterface $documents, private ExpenseRepositoryInterface $expenses, private ReceiptStorageInterface $storage, private ReceiptDocumentParserInterface $parser, private ReceiptMatcher $matcher)
    {
    }

    /** @return array<string, mixed> */
    public function __invoke(UploadReceiptDocumentCommand $command): array
    {
        if (null === $command->mimeType || !isset(self::EXTENSIONS[$command->mimeType])) {
            throw new \DomainException('Seules les factures PDF sont acceptées.');
        }
        if ($command->size > self::MAX_SIZE) {
            throw new \DomainException('Fichier trop volumineux (20 Mo maximum).');
        }
        $sha = hash_file('sha256', $command->sourcePath);
        if (!is_string($sha)) {
            throw new \RuntimeException('Impossible de calculer l’empreinte du fichier.');
        }
        if (null !== $this->documents->findByFingerprint($command->personId, $sha)) {
            throw new \DomainException('Cette facture est déjà présente dans le coffre.');
        }
        $document = new ReceiptDocument($command->personId, $command->originalName, $command->mimeType, $sha);
        $path = $this->storage->store($document->id(), $command->sourcePath, self::EXTENSIONS[$command->mimeType]);
        $this->documents->saveDocument($document);
        $document->startAnalysis();
        try {
            $parsed = $this->parser->parse($path, $command->mimeType);
            $document->completeAnalysis(['supplier' => $parsed->supplier, 'invoiceNumber' => $parsed->invoiceNumber, 'invoiceDate' => $parsed->invoiceDate, 'periodStart' => $parsed->periodStart, 'periodEnd' => $parsed->periodEnd, 'totalAmount' => $parsed->totalAmount]);
            $from = $parsed->periodStart ?? new \DateTimeImmutable('-1 year');
            $to = $parsed->periodEnd ?? new \DateTimeImmutable('+1 year');
            $expenses = $this->expenses->findByPersonAndPeriod($command->personId, $from, $to);
            $linesByDate = [];
            foreach ($parsed->lines as $index => $data) {
                $line = new ReceiptLine($document->id(), $index + 1, ...array_values($data));
                $this->documents->saveLine($line);
                $linesByDate[$line->date()->format('Y-m-d')][] = $line;
            }
            foreach ($linesByDate as $lines) {
                $total = array_sum(array_map(static fn (ReceiptLine $line): float => $line->amountTtc(), $lines));
                $dailyExpense = $this->matcher->dailyTotalMatch($lines[0]->date(), $total, $expenses);
                if (null !== $dailyExpense) {
                    foreach ($lines as $line) {
                        $this->documents->saveMatch(new ReceiptMatch($line->id(), $dailyExpense->id()->value(), 90, ReceiptMatchStatus::Confirmed, ['date exacte', 'total journalier exact']));
                    }
                    continue;
                }
                foreach ($lines as $line) {
                    $candidate = $this->matcher->bestMatch($line, $expenses);
                    if (null !== $candidate) {
                        $status = $candidate['score'] >= 90 ? ReceiptMatchStatus::Confirmed : ReceiptMatchStatus::Suggested;
                        $this->documents->saveMatch(new ReceiptMatch($line->id(), $candidate['expense']->id()->value(), $candidate['score'], $status, $candidate['reasons']));
                    }
                }
            }
        } catch (\Throwable $exception) {
            $document->failAnalysis($exception->getMessage());
        }
        $this->documents->flush();

        return $document->toArray();
    }
}
