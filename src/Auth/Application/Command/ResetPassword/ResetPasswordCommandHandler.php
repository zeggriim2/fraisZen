<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\ResetPassword;

use App\Auth\Domain\Entity\User;
use App\Auth\Domain\Exception\InvalidResetTokenException;
use App\Auth\Domain\Repository\UserRepositoryInterface;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class ResetPasswordCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private UserPasswordHasherInterface $hasher,
    ) {
    }

    public function __invoke(ResetPasswordCommand $command): void
    {
        try {
            /** @var User $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($command->token);
        } catch (ResetPasswordExceptionInterface) {
            throw InvalidResetTokenException::create();
        }

        $this->resetPasswordHelper->removeResetRequest($command->token);

        $user->setPassword($this->hasher->hashPassword($user, $command->newPassword));
        $this->userRepository->save($user);
    }
}
