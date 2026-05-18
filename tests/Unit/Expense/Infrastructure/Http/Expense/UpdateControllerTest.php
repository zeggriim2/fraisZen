<?php

declare(strict_types=1);

use App\Expense\Application\Command\UpdateExpense\UpdateExpenseCommand;
use App\Expense\Infrastructure\Http\Expense\UpdateController;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\Request;

function makeUpdateContainer(): \Psr\Container\ContainerInterface
{
    return new class implements \Psr\Container\ContainerInterface {
        public function get(string $id): mixed { return null; }
        public function has(string $id): bool { return false; }
    };
}

it('dispatche UpdateExpenseCommand et retourne 200', function () {
    $bus = new class implements CommandBusInterface {
        public ?UpdateExpenseCommand $last = null;
        public function dispatch(object $command): mixed { $this->last = $command; return null; }
    };

    $controller = new UpdateController($bus);
    $controller->setContainer(makeUpdateContainer());

    $fields = ['distanceKm' => 20, 'roundTrip' => false];
    $request = Request::create('/api/expenses/uuid-1', 'PATCH', [], [], [], [], json_encode($fields));

    $response = ($controller)('uuid-1', $request);

    expect($response->getStatusCode())->toBe(200);
    expect($bus->last->id)->toBe('uuid-1');
    expect($bus->last->fields)->toBe($fields);
});

it('retourne success true dans le body', function () {
    $bus = new class implements CommandBusInterface {
        public function dispatch(object $command): mixed { return null; }
    };

    $controller = new UpdateController($bus);
    $controller->setContainer(makeUpdateContainer());

    $request = Request::create('/api/expenses/uuid-1', 'PATCH', [], [], [], [], json_encode([]));
    $response = ($controller)('uuid-1', $request);

    expect(json_decode($response->getContent(), true))->toBe(['success' => true]);
});
