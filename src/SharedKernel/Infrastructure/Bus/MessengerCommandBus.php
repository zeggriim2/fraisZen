<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Bus;

use App\SharedKernel\Application\Bus\CommandBusInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class MessengerCommandBus implements CommandBusInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {
    }

    #[\Override]
    public function dispatch(object $command): mixed
    {
        try {
            $envelope = $this->commandBus->dispatch($command);

            return $envelope->last(HandledStamp::class)?->getResult();
        } catch (HandlerFailedException $e) {
            throw $e->getPrevious() ?? $e;
        }
    }
}
