<?php

declare(strict_types=1);

namespace App\Person\Application\Command\CreatePerson;

use App\Person\Domain\Entity\Person;
use App\Person\Domain\Repository\PersonRepositoryInterface;
use App\Person\Domain\ValueObject\PersonId;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreatePersonCommandHandler implements CommandHandlerInterface
{
    public function __construct(private PersonRepositoryInterface $repository) {}

    public function __invoke(CreatePersonCommand $command): string
    {
        $id = PersonId::generate();
        $this->repository->save(new Person($id, $command->userId, $command->firstName, $command->lastName, $command->email));
        return $id->value();
    }
}
