<?php

declare(strict_types=1);

namespace App\Person\Domain\Entity;

use App\Person\Domain\ValueObject\PersonId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;

#[ORM\Entity]
#[ORM\Table(name: 'person')]
#[ORM\Index(name: 'IDX_PERSON_USER', columns: ['user_id'])]
#[Gedmo\SoftDeleteable()]
class Person
{
    use SoftDeleteableEntity;

    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $firstName;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $lastName;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $email;

    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $userId;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $favorite = false;

    public function __construct(
        PersonId $id,
        string $userId,
        string $firstName,
        string $lastName,
        ?string $email = null,
        bool $favorite = false,
    ) {
        if ('' === trim($firstName)) {
            throw new \InvalidArgumentException('First name cannot be empty.');
        }
        if ('' === trim($lastName)) {
            throw new \InvalidArgumentException('Last name cannot be empty.');
        }

        $this->id = $id->value();
        $this->userId = $userId;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->favorite = $favorite;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): PersonId
    {
        return PersonId::fromString($this->id);
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function fullName(): string
    {
        return $this->firstName.' '.$this->lastName;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isFavorite(): bool
    {
        return $this->favorite;
    }

    public function setFavorite(bool $favorite): void
    {
        $this->favorite = $favorite;
    }

    public function update(string $firstName, string $lastName, ?string $email, bool $favorite = false): void
    {
        if ('' === trim($firstName)) {
            throw new \InvalidArgumentException('First name cannot be empty.');
        }
        if ('' === trim($lastName)) {
            throw new \InvalidArgumentException('Last name cannot be empty.');
        }

        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->favorite = $favorite;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'userId' => $this->userId,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'fullName' => $this->fullName(),
            'email' => $this->email,
            'favorite' => $this->favorite,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
