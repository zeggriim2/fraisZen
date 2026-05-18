<?php

declare(strict_types=1);

use App\Expense\Application\Query\GetExpensesSummary\GetExpensesSummaryQuery;
use App\Expense\Infrastructure\Http\Summary\GetController;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\Request;

function makeSummaryGetContainer(): \Psr\Container\ContainerInterface
{
    return new class implements \Psr\Container\ContainerInterface {
        public function get(string $id): mixed { return null; }
        public function has(string $id): bool { return false; }
    };
}

it('retourne 200 avec le résumé', function () {
    $summary = ['total' => 1200.50, 'trips' => 42];

    $queryBus = new class($summary) implements QueryBusInterface {
        public function __construct(private array $result) {}
        public function ask(object $query): mixed { return $this->result; }
    };

    $controller = new GetController($queryBus);
    $controller->setContainer(makeSummaryGetContainer());

    $response = ($controller)(Request::create('/api/expenses/summary', 'GET', [
        'personId' => 'pid-1',
        'year' => '2025',
    ]));

    expect($response->getStatusCode())->toBe(200);
    expect(json_decode($response->getContent(), true))->toBe($summary);
});

it('transmet personId et year à la query', function () {
    $queryBus = new class implements QueryBusInterface {
        public ?GetExpensesSummaryQuery $last = null;
        public function ask(object $query): mixed { $this->last = $query; return []; }
    };

    $controller = new GetController($queryBus);
    $controller->setContainer(makeSummaryGetContainer());

    ($controller)(Request::create('/api/expenses/summary', 'GET', [
        'personId' => 'pid-42',
        'year' => '2024',
    ]));

    expect($queryBus->last->personId)->toBe('pid-42');
    expect($queryBus->last->year)->toBe(2024);
});

it('retourne 400 quand personId est absent', function () {
    $queryBus = new class implements QueryBusInterface {
        public function ask(object $query): mixed { return []; }
    };

    $controller = new GetController($queryBus);
    $controller->setContainer(makeSummaryGetContainer());

    $response = ($controller)(Request::create('/api/expenses/summary'));

    expect($response->getStatusCode())->toBe(400);
    expect(json_decode($response->getContent(), true)['error'])->toContain('personId');
});

it('utilise l\'année courante par défaut', function () {
    $queryBus = new class implements QueryBusInterface {
        public ?GetExpensesSummaryQuery $last = null;
        public function ask(object $query): mixed { $this->last = $query; return []; }
    };

    $controller = new GetController($queryBus);
    $controller->setContainer(makeSummaryGetContainer());

    ($controller)(Request::create('/api/expenses/summary', 'GET', ['personId' => 'pid-1']));

    expect($queryBus->last->year)->toBe((int) date('Y'));
});
