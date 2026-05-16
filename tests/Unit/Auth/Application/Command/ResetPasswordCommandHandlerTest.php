<?php

declare(strict_types=1);

use App\Auth\Application\Command\ResetPassword\ResetPasswordCommand;
use App\Auth\Application\Command\ResetPassword\ResetPasswordCommandHandler;
use App\Auth\Domain\Entity\User;
use App\Auth\Domain\Exception\InvalidResetTokenException;
use App\Auth\Domain\Repository\UserRepositoryInterface;
use App\Auth\Domain\ValueObject\UserId;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

it('change le mot de passe et appelle save() avec un token valide', function () {
    $user = new User(
        UserId::fromString('550e8400-e29b-41d4-a716-446655440001'),
        'user@example.com',
        'old_hashed_password',
    );
    $saved = null;

    $repo = new class($user, $saved) implements UserRepositoryInterface {
        public function __construct(private readonly User $user, private ?User &$saved) {}

        public function save(User $u): void { $this->saved = $u; }
        public function delete(User $u): void {}
        public function findById(UserId $id): ?User { return null; }
        public function findByEmail(string $email): ?User { return $this->user; }
        public function findByStripeCustomerId(string $id): ?User { return null; }
        public function findAll(): array { return []; }
        public function findByEmailLike(string $s): array { return []; }
        public function count(): int { return 0; }
    };

    $helper = new class($user) implements ResetPasswordHelperInterface {
        public function __construct(private readonly User $user) {}

        public function validateTokenAndFetchUser(string $fullToken): object { return $this->user; }
        public function removeResetRequest(string $fullToken): void {}
        public function generateResetToken(object $user): ResetPasswordToken { return new ResetPasswordToken('dummy', new \DateTimeImmutable('+24 hours'), time()); }
        public function getTokenLifetime(): int { return 86400; }
        public function getTokenCount(): int { return 0; }
    };

    $hasher = new class implements UserPasswordHasherInterface {
        public function hashPassword(PasswordAuthenticatedUserInterface $user, string $plainPassword): string
        {
            return 'new_hashed_'.$plainPassword;
        }

        public function isPasswordValid(PasswordAuthenticatedUserInterface $user, string $plainPassword): bool { return true; }
        public function needsRehash(PasswordAuthenticatedUserInterface $user): bool { return false; }
    };

    $handler = new ResetPasswordCommandHandler($repo, $helper, $hasher);
    $handler(new ResetPasswordCommand('valid-token', 'newpassword123'));

    expect($saved)->not->toBeNull();
    expect($saved->getPassword())->toBe('new_hashed_newpassword123');
});

it('consomme le token à usage unique après réinitialisation', function () {
    $user = new User(
        UserId::fromString('550e8400-e29b-41d4-a716-446655440002'),
        'user@example.com',
        'old_hashed_password',
    );
    $removedToken = null;

    $repo = new class($user) implements UserRepositoryInterface {
        public function __construct(private readonly User $user) {}

        public function save(User $u): void {}
        public function delete(User $u): void {}
        public function findById(UserId $id): ?User { return null; }
        public function findByEmail(string $email): ?User { return $this->user; }
        public function findByStripeCustomerId(string $id): ?User { return null; }
        public function findAll(): array { return []; }
        public function findByEmailLike(string $s): array { return []; }
        public function count(): int { return 0; }
    };

    $helper = new class($user, $removedToken) implements ResetPasswordHelperInterface {
        public function __construct(private readonly User $user, private ?string &$removedToken) {}

        public function validateTokenAndFetchUser(string $fullToken): object { return $this->user; }
        public function removeResetRequest(string $fullToken): void { $this->removedToken = $fullToken; }
        public function generateResetToken(object $user): ResetPasswordToken { return new ResetPasswordToken('dummy', new \DateTimeImmutable('+24 hours'), time()); }
        public function getTokenLifetime(): int { return 86400; }
        public function getTokenCount(): int { return 0; }
    };

    $hasher = new class implements UserPasswordHasherInterface {
        public function hashPassword(PasswordAuthenticatedUserInterface $user, string $plainPassword): string { return 'hashed'; }
        public function isPasswordValid(PasswordAuthenticatedUserInterface $user, string $plainPassword): bool { return true; }
        public function needsRehash(PasswordAuthenticatedUserInterface $user): bool { return false; }
    };

    $handler = new ResetPasswordCommandHandler($repo, $helper, $hasher);
    $handler(new ResetPasswordCommand('used-token', 'newpassword123'));

    expect($removedToken)->toBe('used-token');
});

it('lève une DomainException si le token est invalide ou expiré', function () {
    $invalidTokenException = new class('Token invalide') extends \RuntimeException implements ResetPasswordExceptionInterface {
        public function getReason(): string { return 'Token invalide'; }
    };

    $repo = new class implements UserRepositoryInterface {
        public function save(User $u): void {}
        public function delete(User $u): void {}
        public function findById(UserId $id): ?User { return null; }
        public function findByEmail(string $email): ?User { return null; }
        public function findByStripeCustomerId(string $id): ?User { return null; }
        public function findAll(): array { return []; }
        public function findByEmailLike(string $s): array { return []; }
        public function count(): int { return 0; }
    };

    $helper = new class($invalidTokenException) implements ResetPasswordHelperInterface {
        public function __construct(private readonly \Throwable $ex) {}

        public function validateTokenAndFetchUser(string $fullToken): object { throw $this->ex; }
        public function removeResetRequest(string $fullToken): void {}
        public function generateResetToken(object $user): ResetPasswordToken { return new ResetPasswordToken('dummy', new \DateTimeImmutable('+24 hours'), time()); }
        public function getTokenLifetime(): int { return 86400; }
        public function getTokenCount(): int { return 0; }
    };

    $hasher = new class implements UserPasswordHasherInterface {
        public function hashPassword(PasswordAuthenticatedUserInterface $user, string $plainPassword): string { return 'hashed'; }
        public function isPasswordValid(PasswordAuthenticatedUserInterface $user, string $plainPassword): bool { return true; }
        public function needsRehash(PasswordAuthenticatedUserInterface $user): bool { return false; }
    };

    $handler = new ResetPasswordCommandHandler($repo, $helper, $hasher);

    expect(fn () => $handler(new ResetPasswordCommand('invalid-token', 'newpassword123')))
        ->toThrow(InvalidResetTokenException::class);
});

it("ne modifie pas le mot de passe si le token est invalide", function () {
    $user = new User(
        UserId::fromString('550e8400-e29b-41d4-a716-446655440003'),
        'user@example.com',
        'old_hashed_password',
    );
    $saveCount = 0;

    $invalidTokenException = new class('Token expiré') extends \RuntimeException implements ResetPasswordExceptionInterface {
        public function getReason(): string { return 'Token expiré'; }
    };

    $repo = new class($user, $saveCount) implements UserRepositoryInterface {
        public function __construct(private readonly User $user, private int &$saveCount) {}

        public function save(User $u): void { ++$this->saveCount; }
        public function delete(User $u): void {}
        public function findById(UserId $id): ?User { return null; }
        public function findByEmail(string $email): ?User { return $this->user; }
        public function findByStripeCustomerId(string $id): ?User { return null; }
        public function findAll(): array { return []; }
        public function findByEmailLike(string $s): array { return []; }
        public function count(): int { return 0; }
    };

    $helper = new class($invalidTokenException) implements ResetPasswordHelperInterface {
        public function __construct(private readonly \Throwable $ex) {}

        public function validateTokenAndFetchUser(string $fullToken): object { throw $this->ex; }
        public function removeResetRequest(string $fullToken): void {}
        public function generateResetToken(object $user): ResetPasswordToken { return new ResetPasswordToken('dummy', new \DateTimeImmutable('+24 hours'), time()); }
        public function getTokenLifetime(): int { return 86400; }
        public function getTokenCount(): int { return 0; }
    };

    $hasher = new class implements UserPasswordHasherInterface {
        public function hashPassword(PasswordAuthenticatedUserInterface $user, string $plainPassword): string { return 'hashed'; }
        public function isPasswordValid(PasswordAuthenticatedUserInterface $user, string $plainPassword): bool { return true; }
        public function needsRehash(PasswordAuthenticatedUserInterface $user): bool { return false; }
    };

    $handler = new ResetPasswordCommandHandler($repo, $helper, $hasher);

    try {
        $handler(new ResetPasswordCommand('expired-token', 'newpassword123'));
    } catch (InvalidResetTokenException) {
    }

    expect($saveCount)->toBe(0);
    expect($user->getPassword())->toBe('old_hashed_password');
});
