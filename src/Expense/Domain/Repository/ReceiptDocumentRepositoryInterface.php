<?php

declare(strict_types=1);

namespace App\Expense\Domain\Repository;

use App\Expense\Domain\Entity\ReceiptDocument;
use App\Expense\Domain\Entity\ReceiptLine;
use App\Expense\Domain\Entity\ReceiptMatch;

interface ReceiptDocumentRepositoryInterface
{
    public function saveDocument(ReceiptDocument $document): void;

    public function findDocument(string $id): ?ReceiptDocument;

    public function findByFingerprint(string $personId, string $sha256): ?ReceiptDocument;

    public function deleteDocument(ReceiptDocument $document): void;

    public function flush(): void;

    /** @return ReceiptDocument[] */
    public function findPage(string $personId, int $year, int $offset, int $limit): array;

    public function count(string $personId, int $year): int;

    public function saveLine(ReceiptLine $line): void;

    /** @return ReceiptLine[] */
    public function findLines(string $documentId): array;

    public function findLine(string $id): ?ReceiptLine;

    public function saveMatch(ReceiptMatch $match): void;

    public function findMatch(string $id): ?ReceiptMatch;

    /**
     * @param list<string> $lineIds
     *
     * @return ReceiptMatch[]
     */
    public function findMatchesForLines(array $lineIds): array;
}
