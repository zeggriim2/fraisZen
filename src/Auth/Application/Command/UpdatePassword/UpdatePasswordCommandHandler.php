<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\UpdatePassword;

use App\Auth\Domain\Repository\UserRepositoryInterface;
use App\Auth\Domain\ValueObject\UserId;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Webmozart\Assert\Assert;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdatePasswordCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private UserPasswordHasherInterface $hasher,
    ) {
    }

    public function __invoke(UpdatePasswordCommand $command): void
    {
        $user = $this->repository->findById(UserId::fromString($command->userId));
        Assert::notNull($user);

        if (!$this->hasher->isPasswordValid($user, $command->currentPassword)) {
            throw new \DomainException('Mot de passe actuel incorrect.');
        }

        $user->setPassword($this->hasher->hashPassword($user, $command->newPassword));
        $this->repository->save($user);
    }
}
