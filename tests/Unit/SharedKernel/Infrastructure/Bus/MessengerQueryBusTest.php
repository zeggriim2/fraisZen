<?php

declare(strict_types=1);

use App\SharedKernel\Infrastructure\Bus\MessengerQueryBus;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

function makeQueryBus(MessageBusInterface $inner): MessengerQueryBus
{
    return new MessengerQueryBus($inner);
}

it('retourne le résultat du handler via HandledStamp', function () {
    $query = new \stdClass();
    $stamp = new HandledStamp(['data' => 'value'], 'handler');
    $envelope = new Envelope($query, [$stamp]);

    $inner = new class($envelope) implements MessageBusInterface {
        public function __construct(private readonly Envelope $envelope) {}

        public function dispatch(object $message, array $stamps = []): Envelope
        {
            return $this->envelope;
        }
    };

    $result = makeQueryBus($inner)->ask($query);

    expect($result)->toBe(['data' => 'value']);
});

it('retourne null quand aucun HandledStamp', function () {
    $query = new \stdClass();
    $envelope = new Envelope($query);

    $inner = new class($envelope) implements MessageBusInterface {
        public function __construct(private readonly Envelope $envelope) {}

        public function dispatch(object $message, array $stamps = []): Envelope
        {
            return $this->envelope;
        }
    };

    $result = makeQueryBus($inner)->ask($query);

    expect($result)->toBeNull();
});

it('déroule HandlerFailedException et re-lève la cause originale', function () {
    $cause = new class('Not found') extends \App\SharedKernel\Domain\Exception\NotFoundException {};
    $query = new \stdClass();

    $inner = new class($cause, $query) implements MessageBusInterface {
        public function __construct(
            private readonly \Throwable $cause,
            private readonly object $query,
        ) {}

        public function dispatch(object $message, array $stamps = []): Envelope
        {
            $envelope = new Envelope($this->query);
            throw new HandlerFailedException($envelope, [$this->cause]);
        }
    };

    expect(fn () => makeQueryBus($inner)->ask($query))
        ->toThrow(\DomainException::class);
});

it('laisse passer les exceptions non-HandlerFailed directement', function () {
    $query = new \stdClass();

    $inner = new class implements MessageBusInterface {
        public function dispatch(object $message, array $stamps = []): Envelope
        {
            throw new \RuntimeException('Infra error');
        }
    };

    expect(fn () => makeQueryBus($inner)->ask($query))
        ->toThrow(\RuntimeException::class, 'Infra error');
});
