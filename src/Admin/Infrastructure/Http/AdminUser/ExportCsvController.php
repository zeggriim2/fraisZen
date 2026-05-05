<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminUser;

use App\Admin\Application\Query\ExportUsersCsv\ExportUsersCsvQuery;
use App\Admin\Infrastructure\Http\AbstractAdminController;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Webmozart\Assert\Assert;

#[Route('/users/export', name: 'exportCsv', methods: [Request::METHOD_GET])]
class ExportCsvController extends AbstractAdminController
{
    public function __construct(private readonly QueryBusInterface $queryBus)
    {
    }

    public function __invoke(): StreamedResponse
    {
        $rows = $this->queryBus->ask(new ExportUsersCsvQuery());

        $response = new StreamedResponse(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            Assert::resource($handle);
            fputcsv($handle, ['ID', 'Email', 'Statut', 'Inscription', 'Nb personnes']);

            foreach ($rows as $row) {
                fputcsv($handle, [$row['id'], $row['email'], $row['status'], $row['createdAt'], $row['personCount']]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="users-'.date('Y-m-d').'.csv"');

        return $response;
    }
}
