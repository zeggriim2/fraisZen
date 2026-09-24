<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Receipt;

use App\Expense\Application\Receipt\ParsedReceiptDocument;
use App\Expense\Application\Receipt\ReceiptDocumentParserInterface;

/** @psalm-suppress UnusedClass Instantiated by Symfony DI. */
final class FulliReceiptDocumentParser implements ReceiptDocumentParserInterface
{
    public function parse(string $path, string $mimeType): ParsedReceiptDocument
    {
        if (!str_contains($mimeType, 'pdf')) {
            throw new \DomainException('L’analyse détaillée est actuellement disponible pour les factures PDF Fulli.');
        }
        $target = tempnam(sys_get_temp_dir(), 'receipt-text-');
        if (false === $target) {
            throw new \RuntimeException('Impossible de créer le fichier temporaire.');
        }
        try {
            $command = sprintf('/usr/bin/pdftotext -layout %s %s 2>&1', escapeshellarg($path), escapeshellarg($target));
            exec($command, $output, $code);
            if (0 !== $code) {
                throw new \RuntimeException('Lecture du PDF impossible.');
            } $text = file_get_contents($target);
        } finally {
            @unlink($target);
        }
        if (!is_string($text) || '' === trim($text)) {
            throw new \DomainException('Le PDF ne contient aucun texte exploitable.');
        }
        if (!preg_match('/\bFulli\b/i', $text)) {
            throw new \DomainException('Le format de cette facture n’est pas encore pris en charge.');
        }
        $invoiceNumber = $this->capture('/FACTURE\s+N[°º]\s*([^\s]+)/ui', $text);
        $invoiceDate = $this->dateFromCapture('/Date\s+de\s+facture\s*:?\s*(\d{2}\/\d{2}\/\d{4})/ui', $text);
        [$periodStart,$periodEnd] = $this->period($text, $invoiceDate);
        $lines = [];
        foreach (preg_split('/\R/', $text) ?: [] as $raw) {
            $line = trim($raw);
            if (!preg_match('/^(\d{2})\/(\d{2})\s+(.+?)\s+1\s+([\d ]+[,.]\d{2})\s+([\d ]+[,.]\d{2})\s+([\d ]+[,.]\d)(?:\s+\d+)?$/u', $line, $m)) {
                continue;
            }
            $year = $periodStart?->format('Y') ?? $invoiceDate?->format('Y') ?? date('Y');
            $date = \DateTimeImmutable::createFromFormat('!d/m/Y', $m[1].'/'.$m[2].'/'.$year);
            if (false === $date) {
                continue;
            }
            $routeParts = preg_split('/\s{2,}/', trim($m[3])) ?: [];
            $departure = $routeParts[0] ?? null;
            $arrival = count($routeParts) > 1 ? $routeParts[count($routeParts) - 1] : null;
            $lines[] = ['date' => $date, 'departure' => $departure, 'arrival' => $arrival, 'amountHt' => $this->number($m[4]), 'amountTtc' => $this->number($m[5]), 'distanceKm' => $this->number($m[6]), 'rawText' => $line];
        }
        if ([] === $lines) {
            throw new \DomainException('Aucun trajet de péage n’a été détecté dans la facture.');
        }
        $total = array_sum(array_column($lines, 'amountTtc'));

        return new ParsedReceiptDocument('Fulli', $invoiceNumber, $invoiceDate, $periodStart, $periodEnd, round($total, 2), $lines);
    }

    private function capture(string $pattern, string $text): ?string
    {
        return preg_match($pattern, $text, $m) ? trim($m[1]) : null;
    }

    private function dateFromCapture(string $pattern, string $text): ?\DateTimeImmutable
    {
        $v = $this->capture($pattern, $text);
        if (null === $v) {
            return null;
        }$d = \DateTimeImmutable::createFromFormat('!d/m/Y', $v);

        return false === $d ? null : $d;
    }

    /** @return array{?\DateTimeImmutable, ?\DateTimeImmutable} */
    private function period(string $text, ?\DateTimeImmutable $invoiceDate): array
    {
        $months = ['janvier' => 1, 'février' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6, 'juillet' => 7, 'août' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'décembre' => 12];
        if (preg_match('/Relevé\s+de\s+trajets\s*:\s*([\p{L}]+)\s+(\d{4})/ui', $text, $m)) {
            $month = $months[mb_strtolower($m[1])] ?? null;
            if (null !== $month) {
                $start = new \DateTimeImmutable(sprintf('%04d-%02d-01', (int) $m[2], $month));

                return [$start, $start->modify('last day of this month')];
            }
        }

        return [$invoiceDate?->modify('first day of this month'), $invoiceDate?->modify('last day of this month')];
    }

    private function number(string $value): float
    {
        return (float) str_replace([' ', ','], ['', '.'], trim($value));
    }
}
