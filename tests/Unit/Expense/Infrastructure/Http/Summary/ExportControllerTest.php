<?php

declare(strict_types=1);

use App\Expense\Application\Export\ExportResult;
use App\Expense\Application\Export\SummaryExporterInterface;
use App\Expense\Application\Export\SummaryExporterRegistry;
use App\Expense\Infrastructure\Http\Summary\ExportController;
use App\Person\Domain\Repository\PersonRepositoryInterface;
use App\Person\Domain\Entity\Person;
use App\Person\Domain\ValueObject\PersonId;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\Request;

function makeExportContainer(): \Psr\Container\ContainerInterface
{
    return new class implements \Psr\Container\ContainerInterface {
        public function get(string $id): mixed { return null; }
        public function has(string $id): bool { return false; }
    };
}

function makeExportController(array $summaryData = [], string $format = 'pdf'): ExportController
{
    $queryBus = new class($summaryData) implements QueryBusInterface {
        public function __construct(private array $data) {}
        public function ask(object $query): mixed { return $this->data; }
    };

    $exporter = new class($format) implements SummaryExporterInterface {
        public function __construct(private string $fmt) {}
        public function format(): string { return $this->fmt; }
        public function export(array $data, int $year): ExportResult {
            return new ExportResult('content-'.$this->fmt, 'application/'.$this->fmt, 'export.'.$this->fmt);
        }
    };

    $registry = new SummaryExporterRegistry([$exporter]);

    $personRepo = new class implements PersonRepositoryInterface {
        public function save(Person $person): void {}
        public function delete(Person $person): void {}
        public function findById(PersonId $id): ?Person { return null; }
        public function findAllByUserId(string $userId): array { return []; }
    };

    return new ExportController($queryBus, $registry, $personRepo);
}

it('PDF : retourne 200 avec le bon Content-Type et disposition', function () {
    $controller = makeExportController(format: 'pdf');
    $controller->setContainer(makeExportContainer());

    $response = $controller->pdf(Request::create('/api/expenses/summary/pdf', 'GET', [
        'personId' => '550e8400-e29b-41d4-a716-446655440000',
        'year' => '2025',
    ]));

    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
    expect($response->headers->get('Content-Disposition'))->toContain('export.pdf');
});

it('CSV : retourne 200 avec le bon Content-Type et disposition', function () {
    $controller = makeExportController(format: 'csv');
    $controller->setContainer(makeExportContainer());

    $response = $controller->csv(Request::create('/api/expenses/summary/csv', 'GET', [
        'personId' => '550e8400-e29b-41d4-a716-446655440000',
        'year' => '2025',
    ]));

    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('Content-Type'))->toBe('application/csv');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
    expect($response->headers->get('Content-Disposition'))->toContain('export.csv');
});

it('PDF : retourne 400 quand personId est absent', function () {
    $controller = makeExportController(format: 'pdf');
    $controller->setContainer(makeExportContainer());

    $response = $controller->pdf(Request::create('/api/expenses/summary/pdf'));

    expect($response->getStatusCode())->toBe(400);
});

it('CSV : retourne 400 quand personId est absent', function () {
    $controller = makeExportController(format: 'csv');
    $controller->setContainer(makeExportContainer());

    $response = $controller->csv(Request::create('/api/expenses/summary/csv'));

    expect($response->getStatusCode())->toBe(400);
});

it('PDF : injecte personName dans les données transmises à l\'exporter', function () {
    $exportedData = null;

    $queryBus = new class implements QueryBusInterface {
        public function ask(object $query): mixed { return ['total' => 100]; }
    };

    $exporter = new class($exportedData) implements SummaryExporterInterface {
        public array $received = [];
        public function format(): string { return 'pdf'; }
        public function export(array $data, int $year): ExportResult {
            $this->received = $data;
            return new ExportResult('', 'application/pdf', 'f.pdf');
        }
    };

    $registry = new SummaryExporterRegistry([$exporter]);

    $personRepo = new class implements PersonRepositoryInterface {
        public function save(Person $person): void {}
        public function delete(Person $person): void {}
        public function findById(PersonId $id): ?Person { return null; }
        public function findAllByUserId(string $userId): array { return []; }
    };

    $controller = new ExportController($queryBus, $registry, $personRepo);
    $controller->setContainer(makeExportContainer());

    $controller->pdf(Request::create('/api/expenses/summary/pdf', 'GET', [
        'personId' => '550e8400-e29b-41d4-a716-446655440000',
        'year' => '2025',
    ]));

    expect($exporter->received)->toHaveKey('personName');
    expect($exporter->received['personName'])->toBe('');
});
