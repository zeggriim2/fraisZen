<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Export;

use App\Expense\Application\Export\SummaryPdfExporterInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

final class DompdfSummaryPdfExporter implements SummaryPdfExporterInterface
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function export(array $data, int $year): Response
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

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="frais-reels-%s.pdf"', $year),
            ]
        );
    }
}
