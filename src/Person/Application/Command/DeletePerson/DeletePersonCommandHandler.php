<?php

declare(strict_types=1);

namespace App\Person\Application\Command\DeletePerson;

use App\Person\Domain\Exception\PersonNotFoundException;
use App\Person\Domain\Repository\PersonRepositoryInterface;
use App\Person\Domain\ValueObject\PersonId;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeletePersonCommandHandler implements CommandHandlerInterface
{
    public function __construct(private PersonRepositoryInterface $repository)
    {
    }

    public function __invoke(DeletePersonCommand $command): void
    {
        $person = $this->repository->findById(PersonId::fromString($command->id))
            ?? throw PersonNotFoundException::withId($command->id);

        $this->repository->delete($person);
    }
}
