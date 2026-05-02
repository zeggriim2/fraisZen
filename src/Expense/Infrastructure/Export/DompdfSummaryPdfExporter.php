<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Export;

use App\Expense\Application\Export\SummaryPdfExporterInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Response;

final class DompdfSummaryPdfExporter implements SummaryPdfExporterInterface
{
    public function export(array $data, int $year): Response
    {
        $fmt = fn (float $v): string => number_format($v, 2, ',', ' ').' €';

        $tripRows = '';
        foreach ($data['travel']['trips'] as $t) {
            $route = ($t['departure'] && $t['arrival'])
                ? htmlspecialchars($t['departure'].' → '.$t['arrival'])
                : htmlspecialchars($t['description'] ?? '—');
            $ar = $t['roundTrip'] ? ' (A/R)' : '';
            $tripRows .= sprintf(
                '<tr><td>%s</td><td>%s%s</td><td>%s km</td><td>%s CV</td></tr>',
                $t['date'], $route, $ar, $t['distanceKm'], $t['vehiclePower'] ?? '—'
            );
        }

        $mealSection = '';
        if (($data['meal']['entries'] ?? 0) > 0) {
            $homeMealFmt = number_format($data['meal']['homeMealValue'], 2, ',', ' ');
            $mealSection = sprintf(
                '<tr><td>Repas professionnels</td><td>%d repas − %s EUR/repas</td><td>%s</td></tr>',
                $data['meal']['entries'], $homeMealFmt, $fmt($data['meal']['deduction'])
            );
        }

        $parkingSection = '';
        if (($data['parking']['entries'] ?? 0) > 0) {
            $parkingSection = sprintf(
                '<tr><td>Parking</td><td>%d entrees</td><td>%s</td></tr>',
                $data['parking']['entries'], $fmt($data['parking']['deduction'])
            );
        }

        $tripCount = count($data['travel']['trips']);
        $totalKm = round($data['travel']['totalKm'], 0);
        $travelDed = $fmt($data['travel']['deduction']);
        $remoteDed = $fmt($data['remoteWork']['deduction']);
        $tollDed = $fmt($data['toll']['deduction']);
        $totalDed = $fmt($data['total']);
        $today = date('d/m/Y');
        $dailyAllowance = number_format($data['remoteWork']['dailyAllowance'], 2, ',', ' ');

        $html = <<<HTML
<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; margin: 30px; }
h1 { font-size: 20px; color: #4f46e5; margin-bottom: 4px; }
.sub { color: #6b7280; font-size: 12px; margin-bottom: 24px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
th { background: #f3f4f6; text-align: left; padding: 6px 8px; font-size: 11px; }
td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; }
.total-row td { font-weight: bold; background: #eef2ff; font-size: 14px; }
.section-title { font-size: 13px; font-weight: bold; color: #374151; margin: 16px 0 6px; }
</style></head><body>
<h1>Recapitulatif Frais Reels - $year</h1>
<div class="sub">Genere le $today - Annee $year</div>
<p class="section-title">Deductions par categorie</p>
<table>
<tr><th>Categorie</th><th>Detail</th><th>Deduction</th></tr>
<tr><td>Trajets</td><td>$tripCount trajets - $totalKm km</td><td>$travelDed</td></tr>
<tr><td>Teletravail</td><td>{$data['remoteWork']['days']} jours x $dailyAllowance EUR</td><td>$remoteDed</td></tr>
<tr><td>Peages</td><td>{$data['toll']['entries']} entrees</td><td>$tollDed</td></tr>
$mealSection
$parkingSection
<tr class="total-row"><td colspan="2">Total deductible $year</td><td>$totalDed</td></tr>
</table>
HTML;

        if (!empty($tripRows)) {
            $html .= '<p class="section-title">Detail des trajets</p>';
            $html .= '<table><tr><th>Date</th><th>Trajet</th><th>Distance</th><th>Puissance</th></tr>';
            $html .= $tripRows;
            $html .= '</table>';
        }

        $html .= '<p style="font-size:10px;color:#9ca3af;margin-top:20px">Calcule selon le bareme kilometrique officiel '.$year.'. Document a conserver en cas de controle fiscal.</p></body></html>';

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
