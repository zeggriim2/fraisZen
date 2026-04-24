<?php

declare(strict_types=1);

namespace App\Person\Domain\Entity;

use App\Person\Domain\ValueObject\PersonId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'person')]
class Person
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 100)]
    private string $firstName;

    #[ORM\Column(type: 'string', length: 100)]
    private string $lastName;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $email;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        PersonId $id,
        string $firstName,
        string $lastName,
        ?string $email = null,
    ) {
        $this->id = $id->value();
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): PersonId
    {
        return PersonId::fromString($this->id);
    }

    public function firstName(): string { return $this->firstName; }
    public function lastName(): string { return $this->lastName; }
    public function email(): ?string { return $this->email; }
    public function fullName(): string { return $this->firstName . ' ' . $this->lastName; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }

    public function update(string $firstName, string $lastName, ?string $email): void
    {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->email = $email;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'fullName' => $this->fullName(),
            'email' => $this->email,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
