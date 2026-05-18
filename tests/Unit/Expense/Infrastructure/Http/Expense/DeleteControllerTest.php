<?php

declare(strict_types=1);

use App\Expense\Application\Command\DeleteExpense\DeleteExpenseCommand;
use App\Expense\Infrastructure\Http\Expense\DeleteController;
use App\SharedKernel\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\Request;

function makeDeleteContainer(): \Psr\Container\ContainerInterface
{
    return new class implements \Psr\Container\ContainerInterface {
        public function get(string $id): mixed { return null; }
        public function has(string $id): bool { return false; }
    };
}

it('dispatche DeleteExpenseCommand et retourne 204', function () {
    $bus = new class implements CommandBusInterface {
        public ?DeleteExpenseCommand $last = null;
        public function dispatch(object $command): mixed { $this->last = $command; return null; }
    };

    $controller = new DeleteController($bus);
    $controller->setContainer(makeDeleteContainer());

    $response = ($controller)('uuid-to-delete');

    expect($response->getStatusCode())->toBe(204);
    expect($bus->last->id)->toBe('uuid-to-delete');
});

it('retourne un body vide en 204', function () {
    $bus = new class implements CommandBusInterface {
        public function dispatch(object $command): mixed { return null; }
    };

    $controller = new DeleteController($bus);
    $controller->setContainer(makeDeleteContainer());

    $response = ($controller)('uuid-1');

    expect($response->getContent())->toBe('null');
});
