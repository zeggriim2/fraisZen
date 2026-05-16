<?php

declare(strict_types=1);

use App\SharedKernel\Infrastructure\Bus\MessengerCommandBus;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

function makeCommandBus(MessageBusInterface $inner): MessengerCommandBus
{
    return new MessengerCommandBus($inner);
}

it('retourne le résultat du handler via HandledStamp', function () {
    $command = new \stdClass();
    $stamp = new HandledStamp('expected-result', 'handler');
    $envelope = new Envelope($command, [$stamp]);

    $inner = new class($envelope) implements MessageBusInterface {
        public function __construct(private readonly Envelope $envelope) {}

        public function dispatch(object $message, array $stamps = []): Envelope
        {
            return $this->envelope;
        }
    };

    $result = makeCommandBus($inner)->dispatch($command);

    expect($result)->toBe('expected-result');
});

it('retourne null quand aucun HandledStamp', function () {
    $command = new \stdClass();
    $envelope = new Envelope($command);

    $inner = new class($envelope) implements MessageBusInterface {
        public function __construct(private readonly Envelope $envelope) {}

        public function dispatch(object $message, array $stamps = []): Envelope
        {
            return $this->envelope;
        }
    };

    $result = makeCommandBus($inner)->dispatch($command);

    expect($result)->toBeNull();
});

it('déroule HandlerFailedException et re-lève la cause originale', function () {
    $cause = new \DomainException('Règle métier violée');
    $command = new \stdClass();

    $inner = new class($cause, $command) implements MessageBusInterface {
        public function __construct(
            private readonly \Throwable $cause,
            private readonly object $command,
        ) {}

        public function dispatch(object $message, array $stamps = []): Envelope
        {
            $envelope = new Envelope($this->command);
            throw new HandlerFailedException($envelope, [$this->cause]);
        }
    };

    expect(fn () => makeCommandBus($inner)->dispatch($command))
        ->toThrow(\DomainException::class, 'Règle métier violée');
});

it('laisse passer les exceptions non-HandlerFailed directement', function () {
    $command = new \stdClass();

    $inner = new class implements MessageBusInterface {
        public function dispatch(object $message, array $stamps = []): Envelope
        {
            throw new \RuntimeException('Infra error');
        }
    };

    expect(fn () => makeCommandBus($inner)->dispatch($command))
        ->toThrow(\RuntimeException::class, 'Infra error');
});
