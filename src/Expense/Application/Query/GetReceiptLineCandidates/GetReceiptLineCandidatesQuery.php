<?php

declare(strict_types=1);

namespace App\Expense\Application\Query\GetReceiptLineCandidates;

final readonly class GetReceiptLineCandidatesQuery
{
    public function __construct(
        public string $documentId,
        public string $lineId,
        public string $personId,
    ) {
    }
}
