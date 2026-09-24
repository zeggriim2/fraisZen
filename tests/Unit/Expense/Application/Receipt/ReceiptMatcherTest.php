<?php

declare(strict_types=1);

use App\Expense\Application\Receipt\ReceiptMatcher;
use App\Expense\Domain\Entity\ReceiptLine;
use App\Expense\Domain\Entity\TollExpense;
use App\Expense\Domain\ValueObject\ExpenseId;

function toll(string $date, float $amount, ?string $departure = null, ?string $arrival = null): TollExpense
{
    return new TollExpense(ExpenseId::generate(), 'person-id', new DateTimeImmutable($date), null, $amount, $departure, $arrival);
}

it('propose un rapprochement à partir de la date et du montant', function (): void {
    $line = new ReceiptLine('document-id', 1, new DateTimeImmutable('2026-08-04'), 'Paris', 'Lyon', 6.17, 7.40, 42.0, 'raw');
    $match = (new ReceiptMatcher())->bestMatch($line, [toll('2026-08-04', 7.40, 'Paris', 'Lyon')]);

    expect($match)->not->toBeNull()
        ->and($match['score'])->toBe(100)
        ->and($match['reasons'])->toContain('date exacte', 'montant exact');
});

it('rapproche un total journalier composé de plusieurs passages', function (): void {
    $expense = toll('2026-08-16', 29.60);
    $match = (new ReceiptMatcher())->dailyTotalMatch(new DateTimeImmutable('2026-08-16'), 29.60, [$expense]);

    expect($match)->toBe($expense);
});

it('ne rapproche pas un total journalier ambigu', function (): void {
    $match = (new ReceiptMatcher())->dailyTotalMatch(new DateTimeImmutable('2026-08-16'), 29.60, [toll('2026-08-16', 29.60), toll('2026-08-16', 29.60)]);

    expect($match)->toBeNull();
});
