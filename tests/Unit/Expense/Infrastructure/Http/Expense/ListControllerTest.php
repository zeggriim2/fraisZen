<?php

declare(strict_types=1);

use App\Expense\Application\Query\GetExpensesByPeriod\GetExpensesByPeriodQuery;
use App\Expense\Infrastructure\Http\Expense\ListController;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\Request;

function makeListContainer(): \Psr\Container\ContainerInterface
{
    return new class implements \Psr\Container\ContainerInterface {
        public function get(string $id): mixed { return null; }
        public function has(string $id): bool { return false; }
    };
}

it('retourne 200 avec les dépenses de la période', function () {
    $expenses = [['id' => 'uuid-1', 'type' => 'travel']];

    $queryBus = new class($expenses) implements QueryBusInterface {
        public function __construct(private array $result) {}
        public function ask(object $query): mixed { return $this->result; }
    };

    $controller = new ListController($queryBus);
    $controller->setContainer(makeListContainer());

    $response = ($controller)(Request::create('/api/expenses'));

    expect($response->getStatusCode())->toBe(200);
    expect(json_decode($response->getContent(), true))->toBe($expenses);
});

it('transmet les paramètres from/to/personId à la query', function () {
    $captured = null;

    $queryBus = new class($captured) implements QueryBusInterface {
        public ?GetExpensesByPeriodQuery $last = null;
        public function ask(object $query): mixed { $this->last = $query; return []; }
    };

    $controller = new ListController($queryBus);
    $controller->setContainer(makeListContainer());

    ($controller)(Request::create('/api/expenses', 'GET', [
        'from' => '2025-01-01',
        'to' => '2025-01-31',
        'personId' => 'pid-42',
    ]));

    expect($queryBus->last->from)->toBe('2025-01-01');
    expect($queryBus->last->to)->toBe('2025-01-31');
    expect($queryBus->last->personId)->toBe('pid-42');
});

it('utilise les dates du mois courant par défaut', function () {
    $queryBus = new class implements QueryBusInterface {
        public ?GetExpensesByPeriodQuery $last = null;
        public function ask(object $query): mixed { $this->last = $query; return []; }
    };

    $controller = new ListController($queryBus);
    $controller->setContainer(makeListContainer());

    ($controller)(Request::create('/api/expenses'));

    expect($queryBus->last->from)->toBe(date('Y-m-01'));
    expect($queryBus->last->to)->toBe(date('Y-m-t'));
    expect($queryBus->last->personId)->toBeNull();
});
