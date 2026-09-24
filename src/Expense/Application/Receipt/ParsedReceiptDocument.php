<?php

declare(strict_types=1);

namespace App\Expense\Application\Receipt;

final readonly class ParsedReceiptDocument
{
    /** @param list<array{date:\DateTimeImmutable,departure:?string,arrival:?string,amountHt:?float,amountTtc:float,distanceKm:?float,rawText:string}> $lines */
    public function __construct(public ?string $supplier, public ?string $invoiceNumber, public ?\DateTimeImmutable $invoiceDate, public ?\DateTimeImmutable $periodStart, public ?\DateTimeImmutable $periodEnd, public ?float $totalAmount, public array $lines)
    {
    }
}
