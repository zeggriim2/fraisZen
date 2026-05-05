<?php

declare(strict_types=1);

namespace App\Auth\Domain\Entity;

use App\Auth\Domain\ValueObject\UserId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Webmozart\Assert\Assert;

#[ORM\Entity]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $email;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $password;

    /** @var array<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $stripeCustomerId = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $subscriptionStatus = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $defaultYear = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $defaultFiscalPower = null;

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

    public function id(): UserId
    {
        return UserId::fromString($this->id);
    }

    public function email(): string
    {
        return $this->email;
    }

    public function stripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function subscriptionStatus(): ?string
    {
        return $this->subscriptionStatus;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function defaultYear(): ?int
    {
        return $this->defaultYear;
    }

    public function defaultFiscalPower(): ?int
    {
        return $this->defaultFiscalPower;
    }

    public function setStripeCustomerId(string $id): void
    {
        $this->stripeCustomerId = $id;
    }

    public function setSubscriptionStatus(string $status): void
    {
        $this->subscriptionStatus = $status;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function setDefaultYear(?int $year): void
    {
        $this->defaultYear = $year;
    }

    public function setDefaultFiscalPower(?int $power): void
    {
        $this->defaultFiscalPower = $power;
    }

    /** @return non-empty-string */
    public function getUserIdentifier(): string
    {
        Assert::stringNotEmpty($this->email);

        return $this->email;
    }

    /** @return array<string> */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
    }

    public function promoteToAdmin(): void
    {
        if (!in_array('ROLE_ADMIN', $this->roles, true)) {
            $this->roles[] = 'ROLE_ADMIN';
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'roles' => $this->getRoles(),
            'subscriptionStatus' => $this->subscriptionStatus,
            'defaultYear' => $this->defaultYear,
            'defaultFiscalPower' => $this->defaultFiscalPower,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
