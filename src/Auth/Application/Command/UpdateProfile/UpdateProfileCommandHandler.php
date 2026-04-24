<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\UpdateProfile;

use App\Auth\Domain\Exception\UserAlreadyExistsException;
use App\Auth\Domain\Repository\UserRepositoryInterface;
use App\Auth\Domain\ValueObject\UserId;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateProfileCommandHandler implements CommandHandlerInterface
{
    public function __construct(private UserRepositoryInterface $repository) {}

    public function __invoke(UpdateProfileCommand $command): void
    {
        $user = $this->repository->findById(UserId::fromString($command->userId));

        if ($user->email() !== $command->email) {
            $existing = $this->repository->findByEmail($command->email);
            if ($existing !== null) {
                throw UserAlreadyExistsException::withEmail($command->email);
            }
            $user->setEmail($command->email);
        }

        $user->setDefaultYear($command->defaultYear);
        $user->setDefaultFiscalPower($command->defaultFiscalPower);

        $this->repository->save($user);
    }
}
