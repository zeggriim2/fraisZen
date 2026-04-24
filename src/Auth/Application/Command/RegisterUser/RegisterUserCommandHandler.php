<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\RegisterUser;

use App\Auth\Domain\Entity\User;
use App\Auth\Domain\Exception\UserAlreadyExistsException;
use App\Auth\Domain\Repository\UserRepositoryInterface;
use App\Auth\Domain\ValueObject\UserId;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RegisterUserCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private UserPasswordHasherInterface $hasher,
    ) {}

    public function __invoke(RegisterUserCommand $command): string
    {
        if ($this->repository->findByEmail($command->email) !== null) {
            throw UserAlreadyExistsException::withEmail($command->email);
        }

        $id = UserId::generate();
        $user = new User($id, $command->email, '');
        $user->setPassword($this->hasher->hashPassword($user, $command->plainPassword));

        $this->repository->save($user);

        return $id->value();
    }
}
