<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Bus;

use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class MessengerQueryBus implements QueryBusInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
    ) {}

    public function ask(object $query): mixed
    {
        $envelope = $this->queryBus->dispatch($query);

        return $envelope->last(HandledStamp::class)?->getResult();
    }
}
