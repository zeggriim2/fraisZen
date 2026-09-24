<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Ocr;

use App\Expense\Application\Ocr\ReceiptOcrExtractorInterface;

/** @psalm-suppress UnusedClass Instantiated by Symfony's dependency injection container. */
final class TesseractReceiptOcrExtractor implements ReceiptOcrExtractorInterface
{
    public function extract(string $path, string $mimeType): array
    {
        $text = str_starts_with($mimeType, 'image/')
            ? $this->run(['/usr/bin/tesseract', $path, 'stdout', '-l', 'fra+eng'])
            : $this->run(['/usr/bin/pdftotext', '-layout', $path, '-']);

        if (null === $text) {
            return ['status' => 'unavailable', 'merchant' => null, 'date' => null, 'amount' => null, 'rawText' => ''];
        }

        $normalized = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        preg_match('/\b(\d{2})[\.\/-](\d{2})[\.\/-](\d{2,4})\b/', $normalized, $dateMatch);
        preg_match_all('/(?<!\d)(\d{1,5}[,.]\d{2})\s*(?:€|EUR)?/iu', $normalized, $amountMatches);
        $amounts = array_map(static fn (string $value): float => (float) str_replace(',', '.', $value), $amountMatches[1]);
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: [])));
        $merchant = isset($lines[0]) ? mb_substr($lines[0], 0, 120) : null;
        $date = null;
        if ([] !== $dateMatch) {
            $year = (int) $dateMatch[3];
            $year = $year < 100 ? 2000 + $year : $year;
            $date = sprintf('%04d-%02d-%02d', $year, (int) $dateMatch[2], (int) $dateMatch[1]);
        }

        return [
            'status' => '' === $normalized ? 'empty' : 'completed',
            'merchant' => $merchant,
            'date' => $date,
            'amount' => [] === $amounts ? null : max($amounts),
            'rawText' => mb_substr($normalized, 0, 4000),
        ];
    }

    /** @param list<string> $command */
    private function run(array $command): ?string
    {
        if (!is_executable($command[0])) {
            return null;
        }

        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return null;
        }

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return 0 === proc_close($process) && is_string($output) ? $output : null;
    }
}
