<?php

declare(strict_types=1);

use App\Expense\Application\Command\CreateMealExpense\CreateMealExpenseCommand;
use App\Expense\Application\Command\CreateParkingExpense\CreateParkingExpenseCommand;
use App\Expense\Application\Command\CreateRemoteWorkExpense\CreateRemoteWorkExpenseCommand;
use App\Expense\Application\Command\CreateTollExpense\CreateTollExpenseCommand;
use App\Expense\Application\Command\CreateTravelExpense\CreateTravelExpenseCommand;
use App\Expense\Infrastructure\Http\Expense\CreateController;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\Request;

function makeCreateContainer(): \Psr\Container\ContainerInterface
{
    return new class implements \Psr\Container\ContainerInterface {
        public function get(string $id): mixed { return null; }
        public function has(string $id): bool { return false; }
    };
}

function makeCreateBus(): CommandBusInterface
{
    return new class implements CommandBusInterface {
        public ?object $last = null;
        public function dispatch(object $command): mixed { $this->last = $command; return 'new-uuid'; }
    };
}

function makeCreateRequest(array $body): Request
{
    return Request::create('/api/expenses', 'POST', [], [], [], [], json_encode($body));
}

it('dispatche CreateTravelExpenseCommand et retourne 201', function () {
    $bus = makeCreateBus();
    $controller = new CreateController($bus);
    $controller->setContainer(makeCreateContainer());

    $response = ($controller)(makeCreateRequest([
        'type' => 'travel',
        'personId' => 'pid-1',
        'date' => '2025-06-01',
        'distanceKm' => 18.0,
        'vehiclePower' => 5,
        'roundTrip' => true,
        'vehicleType' => 'car',
        'isElectric' => false,
    ]));

    expect($response->getStatusCode())->toBe(201);
    expect($bus->last)->toBeInstanceOf(CreateTravelExpenseCommand::class);
    expect($bus->last->distanceKm)->toBe(18.0);
    expect($bus->last->roundTrip)->toBeTrue();
});

it('dispatche CreateRemoteWorkExpenseCommand', function () {
    $bus = makeCreateBus();
    $controller = new CreateController($bus);
    $controller->setContainer(makeCreateContainer());

    ($controller)(makeCreateRequest([
        'type' => 'remote_work',
        'personId' => 'pid-1',
        'date' => '2025-06-01',
    ]));

    expect($bus->last)->toBeInstanceOf(CreateRemoteWorkExpenseCommand::class);
});

it('dispatche CreateTollExpenseCommand', function () {
    $bus = makeCreateBus();
    $controller = new CreateController($bus);
    $controller->setContainer(makeCreateContainer());

    ($controller)(makeCreateRequest([
        'type' => 'toll',
        'personId' => 'pid-1',
        'date' => '2025-06-01',
        'amount' => 4.50,
    ]));

    expect($bus->last)->toBeInstanceOf(CreateTollExpenseCommand::class);
    expect($bus->last->amount)->toBe(4.50);
});

it('dispatche CreateMealExpenseCommand', function () {
    $bus = makeCreateBus();
    $controller = new CreateController($bus);
    $controller->setContainer(makeCreateContainer());

    ($controller)(makeCreateRequest([
        'type' => 'meal',
        'personId' => 'pid-1',
        'date' => '2025-06-01',
        'mealAmount' => 12.50,
        'employerTicketContribution' => 5.0,
        'withoutReceipt' => false,
    ]));

    expect($bus->last)->toBeInstanceOf(CreateMealExpenseCommand::class);
    expect($bus->last->mealAmount)->toBe(12.50);
});

it('dispatche CreateParkingExpenseCommand', function () {
    $bus = makeCreateBus();
    $controller = new CreateController($bus);
    $controller->setContainer(makeCreateContainer());

    ($controller)(makeCreateRequest([
        'type' => 'parking',
        'personId' => 'pid-1',
        'date' => '2025-06-01',
        'amount' => 8.0,
        'location' => 'Gare Montparnasse',
    ]));

    expect($bus->last)->toBeInstanceOf(CreateParkingExpenseCommand::class);
    expect($bus->last->location)->toBe('Gare Montparnasse');
});

it('retourne 400 pour un type inconnu', function () {
    $bus = makeCreateBus();
    $controller = new CreateController($bus);
    $controller->setContainer(makeCreateContainer());

    $response = ($controller)(makeCreateRequest(['type' => 'unknown']));

    expect($response->getStatusCode())->toBe(400);
    expect(json_decode($response->getContent(), true)['error'])->toContain('unknown');
});

it('retourne l\'id créé dans le body', function () {
    $bus = makeCreateBus();
    $controller = new CreateController($bus);
    $controller->setContainer(makeCreateContainer());

    $response = ($controller)(makeCreateRequest([
        'type' => 'remote_work',
        'personId' => 'pid-1',
        'date' => '2025-06-01',
    ]));

    expect(json_decode($response->getContent(), true))->toBe(['id' => 'new-uuid']);
});
