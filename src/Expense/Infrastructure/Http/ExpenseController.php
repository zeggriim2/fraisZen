<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Http;

use App\Expense\Application\Command\CreateMealExpense\CreateMealExpenseCommand;
use App\Expense\Application\Command\CreateRemoteWorkExpense\CreateRemoteWorkExpenseCommand;
use App\Expense\Application\Command\CreateTollExpense\CreateTollExpenseCommand;
use App\Expense\Application\Command\CreateTravelExpense\CreateTravelExpenseCommand;
use App\Expense\Application\Command\DeleteExpense\DeleteExpenseCommand;
use App\Expense\Application\Command\UpdateExpense\UpdateExpenseCommand;
use App\Expense\Application\Query\GetExpensesByPeriod\GetExpensesByPeriodQuery;
use App\Expense\Application\Query\GetExpensesSummary\GetExpensesSummaryQuery;
use App\Expense\Domain\Exception\ExpenseNotFoundException;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/expenses')]
final class ExpenseController extends AbstractController
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {}

    #[Route('', methods: [Request::METHOD_GET])]
    public function list(Request $request): JsonResponse
    {
        $from     = $request->query->get('from', date('Y-m-01'));
        $to       = $request->query->get('to', date('Y-m-t'));
        $personId = $request->query->get('personId') ?: null;

        return $this->json($this->queryBus->ask(new GetExpensesByPeriodQuery($from, $to, $personId)));
    }

    #[Route('/summary/pdf', methods: [Request::METHOD_GET])]
    public function summaryPdf(Request $request): Response
    {
        $personId = $request->query->get('personId', '');
        $year     = (int) $request->query->get('year', (int) date('Y'));

        if (empty($personId)) {
            return new Response('personId is required', Response::HTTP_BAD_REQUEST);
        }

        $data = $this->queryBus->ask(new GetExpensesSummaryQuery($personId, $year));

        $fmt = fn (float $v): string => number_format($v, 2, ',', ' ') . ' €';

        $tripRows = '';
        foreach ($data['travel']['trips'] as $t) {
            $route = ($t['departure'] && $t['arrival'])
                ? htmlspecialchars($t['departure'] . ' → ' . $t['arrival'])
                : htmlspecialchars($t['description'] ?? '—');
            $ar = $t['roundTrip'] ? ' (A/R)' : '';
            $tripRows .= sprintf(
                '<tr><td>%s</td><td>%s%s</td><td>%s km</td><td>%s CV</td></tr>',
                $t['date'], $route, $ar, $t['distanceKm'], $t['vehiclePower'] ?? '—'
            );
        }

        $mealSection = '';
        if (($data['meal']['entries'] ?? 0) > 0) {
            $mealSection = sprintf(
                '<tr><td>Repas professionnels</td><td>%d repas</td><td>%s</td></tr>',
                $data['meal']['entries'], $fmt($data['meal']['deduction'])
            );
        }

        $tripCount      = count($data['travel']['trips']);
        $totalKm        = round($data['travel']['totalKm'], 0);
        $travelDed      = $fmt($data['travel']['deduction']);
        $remoteDed      = $fmt($data['remoteWork']['deduction']);
        $tollDed        = $fmt($data['toll']['deduction']);
        $totalDed       = $fmt($data['total']);
        $today          = date('d/m/Y');
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
<tr class="total-row"><td colspan="2">Total deductible $year</td><td>$totalDed</td></tr>
</table>
HTML;

        if (!empty($tripRows)) {
            $html .= '<p class="section-title">Detail des trajets</p>';
            $html .= '<table><tr><th>Date</th><th>Trajet</th><th>Distance</th><th>Puissance</th></tr>';
            $html .= $tripRows;
            $html .= '</table>';
        }

        $html .= '<p style="font-size:10px;color:#9ca3af;margin-top:20px">Calcule selon le bareme kilometrique officiel ' . $year . '. Document a conserver en cas de controle fiscal.</p></body></html>';

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
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="frais-reels-%s.pdf"', $year),
            ]
        );
    }

    #[Route('/summary/csv', methods: [Request::METHOD_GET])]
    public function summaryCsv(Request $request): StreamedResponse
    {
        $personId = $request->query->get('personId', '');
        $year     = (int) $request->query->get('year', (int) date('Y'));

        if (empty($personId)) {
            return new StreamedResponse(fn () => print('personId is required'), Response::HTTP_BAD_REQUEST);
        }

        $data = $this->queryBus->ask(new GetExpensesSummaryQuery($personId, $year));

        $response = new StreamedResponse(function () use ($data, $year) {
            $handle = fopen('php://output', 'w');
            // BOM UTF-8 pour compatibilité Excel
            fwrite($handle, "\xEF\xBB\xBF");

            // En-tête
            fputcsv($handle, ["Récapitulatif frais réels - $year"], ';');
            fputcsv($handle, [], ';');

            // Totaux par catégorie
            fputcsv($handle, ['Catégorie', 'Détail', 'Déduction (€)'], ';');
            fputcsv($handle, [
                'Trajets',
                count($data['travel']['trips']) . ' trajets — ' . round($data['travel']['totalKm']) . ' km',
                number_format($data['travel']['deduction'], 2, ',', ' '),
            ], ';');
            fputcsv($handle, [
                'Télétravail',
                $data['remoteWork']['days'] . ' jours × ' . number_format($data['remoteWork']['dailyAllowance'], 2, ',', ' ') . ' €',
                number_format($data['remoteWork']['deduction'], 2, ',', ' '),
            ], ';');
            fputcsv($handle, [
                'Péages',
                $data['toll']['entries'] . ' entrées',
                number_format($data['toll']['deduction'], 2, ',', ' '),
            ], ';');
            fputcsv($handle, [
                'Repas',
                $data['meal']['entries'] . ' repas',
                number_format($data['meal']['deduction'], 2, ',', ' '),
            ], ';');
            fputcsv($handle, ['TOTAL DÉDUCTIBLE', '', number_format($data['total'], 2, ',', ' ')], ';');
            fputcsv($handle, [], ';');

            // Détail des trajets
            if (!empty($data['travel']['trips'])) {
                fputcsv($handle, ['Détail des trajets'], ';');
                fputcsv($handle, ['Date', 'Départ', 'Arrivée', 'Description', 'Distance (km)', 'Puissance (CV)', 'A/R'], ';');
                foreach ($data['travel']['trips'] as $t) {
                    fputcsv($handle, [
                        $t['date'],
                        $t['departure'] ?? '',
                        $t['arrival']   ?? '',
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

    #[Route('/summary', methods: [Request::METHOD_GET])]
    public function summary(Request $request): JsonResponse
    {
        $personId = $request->query->get('personId', '');
        $year     = (int) $request->query->get('year', (int) date('Y'));

        if (empty($personId)) {
            return $this->json(['error' => 'personId is required'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->queryBus->ask(new GetExpensesSummaryQuery($personId, $year)));
    }

    #[Route('', methods: [Request::METHOD_POST])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $type = $data['type'] ?? '';

        try {
            $command = match ($type) {
                'travel' => new CreateTravelExpenseCommand(
                    personId: $data['personId'],
                    date: $data['date'],
                    distanceKm: (float) $data['distanceKm'],
                    vehiclePower: isset($data['vehiclePower']) ? (int) $data['vehiclePower'] : null,
                    departure: $data['departure'] ?? null,
                    arrival: $data['arrival'] ?? null,
                    description: $data['description'] ?? null,
                    roundTrip: (bool) ($data['roundTrip'] ?? false),
                    vehicleType: $data['vehicleType'] ?? 'car',
                    isElectric: (bool) ($data['isElectric'] ?? false),
                ),
                'remote_work' => new CreateRemoteWorkExpenseCommand(
                    personId: $data['personId'],
                    date: $data['date'],
                    description: $data['description'] ?? null,
                ),
                'toll' => new CreateTollExpenseCommand(
                    personId: $data['personId'],
                    date: $data['date'],
                    amount: (float) $data['amount'],
                    departure: $data['departure'] ?? null,
                    arrival: $data['arrival'] ?? null,
                    description: $data['description'] ?? null,
                ),
                'meal' => new CreateMealExpenseCommand(
                    personId: $data['personId'],
                    date: $data['date'],
                    mealAmount: (float) $data['mealAmount'],
                    description: $data['description'] ?? null,
                ),
                default => throw new \InvalidArgumentException(sprintf('Unknown expense type "%s"', $type)),
            };
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $this->commandBus->dispatch($command);

        return $this->json(['message' => 'Expense created'], Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: [Request::METHOD_PATCH])]
    public function update(string $id, Request $request): JsonResponse
    {
        $fields = json_decode($request->getContent(), true) ?? [];

        try {
            $this->commandBus->dispatch(new UpdateExpenseCommand($id, $fields));
            return $this->json(['success' => true]);
        } catch (HandlerFailedException $e) {
            if ($e->getPrevious() instanceof ExpenseNotFoundException) {
                return $this->json(['error' => $e->getPrevious()->getMessage()], Response::HTTP_NOT_FOUND);
            }
            throw $e;
        }
    }

    #[Route('/{id}', methods: [Request::METHOD_DELETE])]
    public function delete(string $id): JsonResponse
    {
        try {
            $this->commandBus->dispatch(new DeleteExpenseCommand($id));
            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (HandlerFailedException $e) {
            if ($e->getPrevious() instanceof ExpenseNotFoundException) {
                return $this->json(['error' => $e->getPrevious()->getMessage()], Response::HTTP_NOT_FOUND);
            }
            throw $e;
        }
    }
}
