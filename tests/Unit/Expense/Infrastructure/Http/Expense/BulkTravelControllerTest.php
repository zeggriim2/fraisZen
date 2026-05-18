<?php

declare(strict_types=1);

use App\Expense\Application\Command\BulkCreateTravelExpense\BulkCreateTravelExpenseCommand;
use App\Expense\Infrastructure\Http\Expense\BulkTravelController;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\Request;

function makeBulkContainer(): \Psr\Container\ContainerInterface
{
    return new class implements \Psr\Container\ContainerInterface {
        public function get(string $id): mixed { return null; }
        public function has(string $id): bool { return false; }
    };
}

function makeBulkBus(): CommandBusInterface
{
    return new class implements CommandBusInterface {
        public ?BulkCreateTravelExpenseCommand $last = null;
        public function dispatch(object $command): mixed { $this->last = $command; return 3; }
    };
}

function makeBulkRequest(array $body): Request
{
    return Request::create('/api/expenses/bulk-travel', 'POST', [], [], [], [], json_encode($body));
}

it('dispatche la commande et retourne 201 avec le nombre de créations', function () {
    $bus = makeBulkBus();
    $controller = new BulkTravelController($bus);
    $controller->setContainer(makeBulkContainer());

    $response = ($controller)(makeBulkRequest([
        'personId' => 'pid-1',
        'dates' => ['2025-06-02', '2025-06-03', '2025-06-04'],
        'distanceKm' => 18.0,
        'vehiclePower' => 5,
        'roundTrip' => true,
        'vehicleType' => 'car',
        'isElectric' => false,
    ]));

    expect($response->getStatusCode())->toBe(201);
    expect(json_decode($response->getContent(), true))->toBe(['created' => 3]);
});

it('transmet les dates valides à la commande', function () {
    $bus = makeBulkBus();
    $controller = new BulkTravelController($bus);
    $controller->setContainer(makeBulkContainer());

    ($controller)(makeBulkRequest([
        'personId' => 'pid-1',
        'dates' => ['2025-06-02', '2025-06-03', '2025-06-04'],
        'distanceKm' => 18.0,
    ]));

    expect($bus->last->dates)->toBe(['2025-06-02', '2025-06-03', '2025-06-04']);
    expect($bus->last->personId)->toBe('pid-1');
});

it('filtre les dates au format invalide', function () {
    $bus = makeBulkBus();
    $controller = new BulkTravelController($bus);
    $controller->setContainer(makeBulkContainer());

    ($controller)(makeBulkRequest([
        'personId' => 'pid-1',
        'dates' => ['2025-06-02', 'not-a-date', '2025-06-04', ''],
        'distanceKm' => 18.0,
    ]));

    expect($bus->last->dates)->toBe(['2025-06-02', '2025-06-04']);
});

it('retourne 400 quand dates est absent', function () {
    $bus = makeBulkBus();
    $controller = new BulkTravelController($bus);
    $controller->setContainer(makeBulkContainer());

    $response = ($controller)(makeBulkRequest(['personId' => 'pid-1']));

    expect($response->getStatusCode())->toBe(400);
    expect(json_decode($response->getContent(), true)['error'])->toContain('dates');
});

it('retourne 400 quand personId est absent', function () {
    $bus = makeBulkBus();
    $controller = new BulkTravelController($bus);
    $controller->setContainer(makeBulkContainer());

    $response = ($controller)(makeBulkRequest(['dates' => ['2025-06-02']]));

    expect($response->getStatusCode())->toBe(400);
    expect(json_decode($response->getContent(), true)['error'])->toContain('personId');
});
