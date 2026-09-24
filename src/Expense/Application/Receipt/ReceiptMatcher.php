<?php

declare(strict_types=1);

namespace App\Expense\Application\Receipt;

use App\Expense\Domain\Entity\Expense;
use App\Expense\Domain\Entity\ReceiptLine;
use App\Expense\Domain\Entity\TollExpense;

final class ReceiptMatcher
{
    /** @param Expense[] $expenses */
    public function dailyTotalMatch(\DateTimeImmutable $date, float $total, array $expenses): ?TollExpense
    {
        $matches = array_values(array_filter($expenses, static fn (Expense $expense): bool => $expense instanceof TollExpense
            && $expense->date()->format('Y-m-d') === $date->format('Y-m-d')
            && abs($expense->amount() - $total) < 0.01));

        return 1 === count($matches) ? $matches[0] : null;
    }

    /**
     * @param Expense[] $expenses
     *
     * @return array{expense: TollExpense, score: int, reasons: list<string>}|null
     */
    public function bestMatch(ReceiptLine $line, array $expenses): ?array
    {
        $candidates = $this->rankCandidates($line, $expenses);
        if (($candidates[0]['score'] ?? 0) < 60) {
            return null;
        }
        if (isset($candidates[1]) && $candidates[0]['score'] === $candidates[1]['score']) {
            $candidates[0]['score'] = min(89, $candidates[0]['score']);
        }

        return $candidates[0];
    }

    /**
     * @param Expense[] $expenses
     *
     * @return list<array{expense: TollExpense, score: int, reasons: list<string>}>
     */
    public function rankCandidates(ReceiptLine $line, array $expenses): array
    {
        $candidates = [];
        foreach ($expenses as $expense) {
            if (!$expense instanceof TollExpense) {
                continue;
            }
            $score = 0;
            $reasons = [];
            if ($expense->date()->format('Y-m-d') === $line->date()->format('Y-m-d')) {
                $score += 45;
                $reasons[] = 'date exacte';
            }
            if (abs($expense->amount() - $line->amountTtc()) < 0.01) {
                $score += 35;
                $reasons[] = 'montant exact';
            }
            if ($this->samePlace($expense->departure(), $line->departure())) {
                $score += 10;
                $reasons[] = 'départ similaire';
            }
            if ($this->samePlace($expense->arrival(), $line->arrival())) {
                $score += 10;
                $reasons[] = 'arrivée similaire';
            }
            $candidates[] = ['expense' => $expense, 'score' => $score, 'reasons' => $reasons];
        }
        usort($candidates, static fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return $candidates;
    }

    private function samePlace(?string $left, ?string $right): bool
    {
        if (null === $left || null === $right) {
            return false;
        }
        $normalize = static fn (string $v): string => (string) preg_replace('/[^a-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', mb_strtolower($v)) ?: '');
        $a = $normalize($left);
        $b = $normalize($right);

        return '' !== $a && '' !== $b && (str_contains($a, $b) || str_contains($b, $a));
    }
}
