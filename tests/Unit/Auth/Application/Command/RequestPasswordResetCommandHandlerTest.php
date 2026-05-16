<?php

declare(strict_types=1);

use App\Auth\Application\Command\RequestPasswordReset\RequestPasswordResetCommand;
use App\Auth\Application\Command\RequestPasswordReset\RequestPasswordResetCommandHandler;
use App\Auth\Domain\Entity\User;
use App\Auth\Domain\Repository\UserRepositoryInterface;
use App\Auth\Domain\ValueObject\UserId;
use Psr\Log\NullLogger;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

it("n'envoie pas d'email et ne lève pas d'exception si l'email est inconnu", function () {
    $emailSent = false;

    $repo = new class implements UserRepositoryInterface {
        public function findByEmail(string $email): ?User { return null; }
        public function save(User $u): void {}
        public function delete(User $u): void {}
        public function findById(UserId $id): ?User { return null; }
        public function findByStripeCustomerId(string $id): ?User { return null; }
        public function findAll(): array { return []; }
        public function findByEmailLike(string $s): array { return []; }
        public function count(): int { return 0; }
    };

    $mailer = new class($emailSent) implements MailerInterface {
        public function __construct(private bool &$emailSent) {}

        public function send(RawMessage $message, ?Envelope $envelope = null): void
        {
            $this->emailSent = true;
        }
    };

    $helper = new class implements ResetPasswordHelperInterface {
        public function generateResetToken(object $user): ResetPasswordToken
        {
            throw new \LogicException('Ne devrait pas être appelé');
        }

        public function validateTokenAndFetchUser(string $fullToken): object { return new \stdClass(); }
        public function removeResetRequest(string $fullToken): void {}
        public function getTokenLifetime(): int { return 3600; }
        public function getTokenCount(): int { return 0; }
    };

    $handler = new RequestPasswordResetCommandHandler($repo, $helper, $mailer, new NullLogger(), 'http://localhost');
    $handler(new RequestPasswordResetCommand('inconnu@example.com'));

    expect($emailSent)->toBeFalse();
});

it('envoie un email au bon destinataire quand le compte existe', function () {
    $user = new User(
        UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
        'user@example.com',
        'hashed_password',
    );
    $sentTo = null;

    $repo = new class($user) implements UserRepositoryInterface {
        public function __construct(private readonly User $user) {}

        public function findByEmail(string $email): ?User { return $this->user; }
        public function save(User $u): void {}
        public function delete(User $u): void {}
        public function findById(UserId $id): ?User { return null; }
        public function findByStripeCustomerId(string $id): ?User { return null; }
        public function findAll(): array { return []; }
        public function findByEmailLike(string $s): array { return []; }
        public function count(): int { return 0; }
    };

    $mailer = new class($sentTo) implements MailerInterface {
        public function __construct(private mixed &$sentTo) {}

        public function send(RawMessage $message, ?Envelope $envelope = null): void
        {
            assert($message instanceof TemplatedEmail);
            $this->sentTo = $message->getTo()[0]->getAddress();
        }
    };

    $helper = new class implements ResetPasswordHelperInterface {
        public function generateResetToken(object $user): ResetPasswordToken
        {
            return new ResetPasswordToken('tok', new \DateTimeImmutable('+24 hours'), time());
        }

        public function validateTokenAndFetchUser(string $fullToken): object { return new \stdClass(); }
        public function removeResetRequest(string $fullToken): void {}
        public function getTokenLifetime(): int { return 86400; }
        public function getTokenCount(): int { return 0; }
    };

    $handler = new RequestPasswordResetCommandHandler($repo, $helper, $mailer, new NullLogger(), 'http://localhost');
    $handler(new RequestPasswordResetCommand('user@example.com'));

    expect($sentTo)->toBe('user@example.com');
});

it("n'envoie pas d'email et ne lève pas d'exception en cas de throttling", function () {
    $user = new User(
        UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
        'user@example.com',
        'hashed_password',
    );
    $emailSent = false;
    $loggedWarning = null;

    $repo = new class($user) implements UserRepositoryInterface {
        public function __construct(private readonly User $user) {}

        public function findByEmail(string $email): ?User { return $this->user; }
        public function save(User $u): void {}
        public function delete(User $u): void {}
        public function findById(UserId $id): ?User { return null; }
        public function findByStripeCustomerId(string $id): ?User { return null; }
        public function findAll(): array { return []; }
        public function findByEmailLike(string $s): array { return []; }
        public function count(): int { return 0; }
    };

    $mailer = new class($emailSent) implements MailerInterface {
        public function __construct(private bool &$emailSent) {}

        public function send(RawMessage $message, ?Envelope $envelope = null): void
        {
            $this->emailSent = true;
        }
    };

    $throttleException = new class('Trop de demandes') extends \RuntimeException implements ResetPasswordExceptionInterface {
        public function getReason(): string { return 'Trop de demandes'; }
    };

    $helper = new class($throttleException) implements ResetPasswordHelperInterface {
        public function __construct(private readonly \Throwable $ex) {}

        public function generateResetToken(object $user): ResetPasswordToken { throw $this->ex; }
        public function validateTokenAndFetchUser(string $fullToken): object { return new \stdClass(); }
        public function removeResetRequest(string $fullToken): void {}
        public function getTokenLifetime(): int { return 86400; }
        public function getTokenCount(): int { return 0; }
    };

    $logger = new class($loggedWarning) extends NullLogger {
        public function __construct(private mixed &$loggedWarning) {}

        public function warning(string|\Stringable $message, array $context = []): void
        {
            $this->loggedWarning = ['message' => $message, 'context' => $context];
        }
    };

    $handler = new RequestPasswordResetCommandHandler($repo, $helper, $mailer, $logger, 'http://localhost');
    $handler(new RequestPasswordResetCommand('user@example.com'));

    expect($emailSent)->toBeFalse();
    expect($loggedWarning)->not->toBeNull();
    expect($loggedWarning['message'])->toBe('Password reset throttled.');
    expect($loggedWarning['context']['email'])->toBe('user@example.com');
});

it("l'url de réinitialisation dans l'email contient le token et le frontendUrl", function () {
    $user = new User(
        UserId::fromString('550e8400-e29b-41d4-a716-446655440000'),
        'user@example.com',
        'hashed_password',
    );
    $capturedUrl = null;

    $repo = new class($user) implements UserRepositoryInterface {
        public function __construct(private readonly User $user) {}

        public function findByEmail(string $email): ?User { return $this->user; }
        public function save(User $u): void {}
        public function delete(User $u): void {}
        public function findById(UserId $id): ?User { return null; }
        public function findByStripeCustomerId(string $id): ?User { return null; }
        public function findAll(): array { return []; }
        public function findByEmailLike(string $s): array { return []; }
        public function count(): int { return 0; }
    };

    $mailer = new class($capturedUrl) implements MailerInterface {
        public function __construct(private mixed &$capturedUrl) {}

        public function send(RawMessage $message, ?Envelope $envelope = null): void
        {
            assert($message instanceof TemplatedEmail);
            $this->capturedUrl = $message->getContext()['resetUrl'] ?? null;
        }
    };

    $helper = new class implements ResetPasswordHelperInterface {
        public function generateResetToken(object $user): ResetPasswordToken
        {
            return new ResetPasswordToken('my-secure-token', new \DateTimeImmutable('+24 hours'), time());
        }

        public function validateTokenAndFetchUser(string $fullToken): object { return new \stdClass(); }
        public function removeResetRequest(string $fullToken): void {}
        public function getTokenLifetime(): int { return 86400; }
        public function getTokenCount(): int { return 0; }
    };

    $handler = new RequestPasswordResetCommandHandler($repo, $helper, $mailer, new NullLogger(), 'https://app.example.com');
    $handler(new RequestPasswordResetCommand('user@example.com'));

    expect($capturedUrl)->toBe('https://app.example.com/reset-password?token=my-secure-token');
});
