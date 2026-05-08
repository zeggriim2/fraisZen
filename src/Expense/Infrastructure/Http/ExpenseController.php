<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Http;

use App\Admin\Domain\Repository\FiscalConfigRepositoryInterface;
use App\Expense\Application\Command\CreateMealExpense\CreateMealExpenseCommand;
use App\Expense\Application\Command\CreateParkingExpense\CreateParkingExpenseCommand;
use App\Expense\Application\Command\CreateRemoteWorkExpense\CreateRemoteWorkExpenseCommand;
use App\Expense\Application\Command\CreateTollExpense\CreateTollExpenseCommand;
use App\Expense\Application\Command\CreateTravelExpense\CreateTravelExpenseCommand;
use App\Expense\Application\Command\DeleteExpense\DeleteExpenseCommand;
use App\Expense\Application\Command\UpdateExpense\UpdateExpenseCommand;
use App\Expense\Application\Export\SummaryExporterRegistry;
use App\Expense\Application\Query\GetExpensesByPeriod\GetExpensesByPeriodQuery;
use App\Expense\Application\Query\GetExpensesSummary\GetExpensesSummaryQuery;
use App\Expense\Domain\Exception\ExpenseNotFoundException;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[Route('/api/expenses', name: 'expense_')]
final class ExpenseController extends AbstractController
{
    private const FALLBACK_HOME_MEAL_VALUE = 5.35;
    private const FALLBACK_DAILY_ALLOWANCE = 2.70;

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
        private readonly FiscalConfigRepositoryInterface $fiscalConfigRepository,
        private readonly SummaryExporterRegistry $exporters,
    ) {
    }

    #[Route('/fiscal-config/{year}', name: 'fiscalconfig', requirements: ['year' => '\d{4}'], methods: [Request::METHOD_GET])]
    public function fiscalConfig(int $year): JsonResponse
    {
        $config = $this->fiscalConfigRepository->findByYear($year);

        return $this->json([
            'year' => $year,
            'remoteWorkDailyAllowance' => $config?->remoteWorkDailyAllowance() ?? self::FALLBACK_DAILY_ALLOWANCE,
            'homeMealValue' => $config?->homeMealValue() ?? self::FALLBACK_HOME_MEAL_VALUE,
        ]);
    }

    #[Route('', name: 'list', methods: [Request::METHOD_GET])]
    public function list(Request $request): JsonResponse
    {
        $from = $request->query->get('from', date('Y-m-01'));
        $to = $request->query->get('to', date('Y-m-t'));
        $personId = $request->query->get('personId') ?: null;

        return $this->json($this->queryBus->ask(new GetExpensesByPeriodQuery($from, $to, $personId)));
    }

    #[Route('/summary/pdf', name: 'summaryPdf', methods: [Request::METHOD_GET])]
    public function summaryPdf(Request $request): Response
    {
        $personId = $request->query->get('personId', '');
        $year = (int) $request->query->get('year', (int) date('Y'));

        if (empty($personId)) {
            return new Response('personId is required', Response::HTTP_BAD_REQUEST);
        }

        $data = $this->queryBus->ask(new GetExpensesSummaryQuery($personId, $year));
        $result = $this->exporters->get('pdf')->export($data, $year);

        return new Response($result->content, Response::HTTP_OK, [
            'Content-Type' => $result->mimeType,
            'Content-Disposition' => 'attachment; filename="'.$result->filename.'"',
        ]);
    }

    #[Route('/summary/csv', name: 'summaryCsv', methods: [Request::METHOD_GET])]
    public function summaryCsv(Request $request): Response
    {
        $personId = $request->query->get('personId', '');
        $year = (int) $request->query->get('year', (int) date('Y'));

        if (empty($personId)) {
            return new Response('personId is required', Response::HTTP_BAD_REQUEST);
        }

        $data = $this->queryBus->ask(new GetExpensesSummaryQuery($personId, $year));
        $result = $this->exporters->get('csv')->export($data, $year);

        return new Response($result->content, Response::HTTP_OK, [
            'Content-Type' => $result->mimeType,
            'Content-Disposition' => 'attachment; filename="'.$result->filename.'"',
        ]);
    }

    #[Route('/summary', name: 'summary', methods: [Request::METHOD_GET])]
    public function summary(Request $request): JsonResponse
    {
        $personId = $request->query->get('personId', '');
        $year = (int) $request->query->get('year', (int) date('Y'));

        if (empty($personId)) {
            return $this->json(['error' => 'personId is required'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->queryBus->ask(new GetExpensesSummaryQuery($personId, $year)));
    }

    #[Route('', name: 'create', methods: [Request::METHOD_POST])]
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
                    mealAmount: (float) ($data['mealAmount'] ?? 0),
                    description: $data['description'] ?? null,
                    employerTicketContribution: (float) ($data['employerTicketContribution'] ?? 0),
                    withoutReceipt: (bool) ($data['withoutReceipt'] ?? false),
                ),
                'parking' => new CreateParkingExpenseCommand(
                    personId: $data['personId'],
                    date: $data['date'],
                    amount: (float) ($data['amount'] ?? 0),
                    location: $data['location'] ?? null,
                    description: $data['description'] ?? null,
                ),
                default => throw new \InvalidArgumentException(sprintf('Unknown expense type "%s"', $type)),
            };
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $id = $this->commandBus->dispatch($command);

        return $this->json(['id' => $id], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', requirements: ['id' => Requirement::UUID_V4], methods: [Request::METHOD_PATCH])]
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

    #[Route('/{id}', name: 'delete', requirements: ['id' => Requirement::UUID_V4], methods: [Request::METHOD_DELETE])]
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
