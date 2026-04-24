<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\DeleteUser;

use App\Auth\Domain\Repository\UserRepositoryInterface;
use App\Auth\Domain\ValueObject\UserId;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeleteUserCommandHandler implements CommandHandlerInterface
{
    public function __construct(private UserRepositoryInterface $repository) {}

    public function __invoke(DeleteUserCommand $command): void
    {
        $user = $this->repository->findById(UserId::fromString($command->userId));
        $this->repository->delete($user);
    }
}
