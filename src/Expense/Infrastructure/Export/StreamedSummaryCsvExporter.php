<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Export;

use App\Expense\Application\Export\SummaryCsvExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webmozart\Assert\Assert;

final class StreamedSummaryCsvExporter implements SummaryCsvExporterInterface
{
    public function export(array $data, int $year): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($data, $year) {
            $handle = fopen('php://output', 'w');
            Assert::resource($handle);
            // BOM UTF-8 pour compatibilité Excel
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ["Récapitulatif frais réels - $year"], ';');
            fputcsv($handle, [], ';');

            fputcsv($handle, ['Catégorie', 'Détail', 'Déduction (€)'], ';');
            fputcsv($handle, [
                'Trajets',
                count($data['travel']['trips']).' trajets — '.(int) round($data['travel']['totalKm']).' km',
                number_format($data['travel']['deduction'], 2, ',', ' '),
            ], ';');
            fputcsv($handle, [
                'Télétravail',
                $data['remoteWork']['days'].' jours × '.number_format($data['remoteWork']['dailyAllowance'], 2, ',', ' ').' €',
                number_format($data['remoteWork']['deduction'], 2, ',', ' '),
            ], ';');
            fputcsv($handle, [
                'Péages',
                $data['toll']['entries'].' entrées',
                number_format($data['toll']['deduction'], 2, ',', ' '),
            ], ';');
            fputcsv($handle, [
                'Repas',
                $data['meal']['entries'].' repas − '.number_format($data['meal']['homeMealValue'], 2, ',', ' ').' €/repas',
                number_format($data['meal']['deduction'], 2, ',', ' '),
            ], ';');
            fputcsv($handle, [
                'Parking',
                ($data['parking']['entries'] ?? 0).' entrées',
                number_format($data['parking']['deduction'] ?? 0, 2, ',', ' '),
            ], ';');
            fputcsv($handle, ['TOTAL DÉDUCTIBLE', '', number_format($data['total'], 2, ',', ' ')], ';');
            fputcsv($handle, [], ';');

            if (!empty($data['travel']['trips'])) {
                fputcsv($handle, ['Détail des trajets'], ';');
                fputcsv($handle, ['Date', 'Départ', 'Arrivée', 'Description', 'Distance (km)', 'Puissance (CV)', 'A/R'], ';');
                foreach ($data['travel']['trips'] as $t) {
                    fputcsv($handle, [
                        $t['date'],
                        $t['departure'] ?? '',
                        $t['arrival'] ?? '',
                        $t['description'] ?? '',
                        $t['distanceKm'],
                        $t['vehiclePower'] ?? '',
                        $t['roundTrip'] ? 'Oui' : 'Non',
                    ], ';');
                }
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="frais-reels-%s.csv"', $year));

        return $response;
    }
}
