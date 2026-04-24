<?php

declare(strict_types=1);

namespace App\Auth\Domain\Entity;

use App\Auth\Domain\ValueObject\UserId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private string $email;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $password;

    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $stripeCustomerId = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $subscriptionStatus = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(UserId $id, string $email, string $hashedPassword)
    {
        $this->id = $id->value();
        $this->email = $email;
        $this->password = $hashedPassword;
        $this->roles = ['ROLE_USER'];
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): UserId { return UserId::fromString($this->id); }
    public function email(): string { return $this->email; }
    public function stripeCustomerId(): ?string { return $this->stripeCustomerId; }
    public function subscriptionStatus(): ?string { return $this->subscriptionStatus; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }

    public function setStripeCustomerId(string $id): void { $this->stripeCustomerId = $id; }
    public function setSubscriptionStatus(string $status): void { $this->subscriptionStatus = $status; }

    public function getUserIdentifier(): string { return $this->email; }
    public function getRoles(): array { return $this->roles; }
    public function eraseCredentials(): void {}
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $hashedPassword): void { $this->password = $hashedPassword; }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'roles' => $this->getRoles(),
            'subscriptionStatus' => $this->subscriptionStatus,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
