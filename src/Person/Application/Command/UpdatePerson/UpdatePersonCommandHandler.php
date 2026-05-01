<?php

declare(strict_types=1);

namespace App\Person\Application\Command\UpdatePerson;

use App\Person\Domain\Exception\PersonNotFoundException;
use App\Person\Domain\Repository\PersonRepositoryInterface;
use App\Person\Domain\ValueObject\PersonId;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdatePersonCommandHandler implements CommandHandlerInterface
{
    public function __construct(private PersonRepositoryInterface $repository)
    {
    }

    public function __invoke(UpdatePersonCommand $command): void
    {
        $person = $this->repository->findById(PersonId::fromString($command->id))
            ?? throw PersonNotFoundException::withId($command->id);

        $person->update($command->firstName, $command->lastName, $command->email, $command->favorite);
        $this->repository->save($person);
    }
}
