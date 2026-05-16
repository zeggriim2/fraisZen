<?php

declare(strict_types=1);

namespace App\Auth\Application\Command\RequestPasswordReset;

use App\Auth\Domain\Repository\UserRepositoryInterface;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RequestPasswordResetCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        #[Autowire('%env(DEFAULT_URI)%')]
        private string $frontendUrl,
    ) {
    }

    public function __invoke(RequestPasswordResetCommand $command): void
    {
        $user = $this->userRepository->findByEmail($command->email);

        if (!$user) {
            return;
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->logger->warning('Password reset throttled.', [
                'email' => $command->email,
                'reason' => $e->getReason(),
            ]);

            return;
        }

        $email = new TemplatedEmail()
            ->from('noreply@fraisreel.fr')
            ->to($user->email())
            ->subject('Réinitialisation de votre mot de passe — Frais Réels')
            ->htmlTemplate('auth/reset_password_email.html.twig')
            ->context([
                'resetUrl' => $this->frontendUrl.'/reset-password?token='.$resetToken->getToken(),
                'tokenLifetime' => $this->resetPasswordHelper->getTokenLifetime(),
            ]);

        $this->mailer->send($email);
    }
}
