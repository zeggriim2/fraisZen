<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Http\Summary;

use App\Expense\Application\Export\SummaryExporterRegistry;
use App\Expense\Application\Query\GetExpensesSummary\GetExpensesSummaryQuery;
use App\Person\Domain\Repository\PersonRepositoryInterface;
use App\Person\Domain\ValueObject\PersonId;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ExportController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
        private readonly SummaryExporterRegistry $exporters,
        private readonly PersonRepositoryInterface $personRepository,
    ) {
    }

    #[Route('/pdf', name: 'pdf', methods: [Request::METHOD_GET])]
    public function pdf(Request $request): Response
    {
        $personId = $request->query->get('personId', '');
        $year = (int) $request->query->get('year', (int) date('Y'));

        if (empty($personId)) {
            return new Response('personId is required', Response::HTTP_BAD_REQUEST);
        }

        $data = $this->queryBus->ask(new GetExpensesSummaryQuery($personId, $year));
        $person = $this->personRepository->findById(PersonId::fromString($personId));
        $data['personName'] = $person?->fullName() ?? '';
        $result = $this->exporters->get('pdf')->export($data, $year);

        return new Response($result->content, Response::HTTP_OK, [
            'Content-Type' => $result->mimeType,
            'Content-Disposition' => 'attachment; filename="'.$result->filename.'"',
        ]);
    }

    #[Route('/csv', name: 'csv', methods: [Request::METHOD_GET])]
    public function csv(Request $request): Response
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
}
