<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Export;

use App\Expense\Application\Export\ExportResult;
use App\Expense\Application\Export\SummaryExporterInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

final class DompdfSummaryPdfExporter implements SummaryExporterInterface
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function format(): string
    {
        return 'pdf';
    }

    public function export(array $data, int $year): ExportResult
    {
        $html = $this->twig->render('expense/summary_pdf.html.twig', [
            'data' => $data,
            'year' => $year,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new ExportResult(
            $dompdf->output(),
            'application/pdf',
            sprintf('frais-reels-%s.pdf', $year),
        );
    }
}
