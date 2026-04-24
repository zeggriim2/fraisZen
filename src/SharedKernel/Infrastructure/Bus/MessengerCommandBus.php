<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Bus;

use App\SharedKernel\Application\Bus\CommandBusInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class MessengerCommandBus implements CommandBusInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {}

    public function dispatch(object $command): mixed
    {
        $envelope = $this->commandBus->dispatch($command);
        $result = $envelope->last(\Symfony\Component\Messenger\Stamp\HandledStamp::class);
        return $result?->getResult();
    }
}
