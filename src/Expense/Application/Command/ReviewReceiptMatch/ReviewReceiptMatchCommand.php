<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\ReviewReceiptMatch;

final readonly class ReviewReceiptMatchCommand
{
    public function __construct(public string $matchId, public string $documentId, public string $personId, public bool $confirmed)
    {
    }
}
