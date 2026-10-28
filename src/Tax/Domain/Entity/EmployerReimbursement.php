<?php

declare(strict_types=1);

namespace App\Tax\Domain\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** @psalm-suppress PossiblyUnusedMethod */
#[ORM\Entity]
#[ORM\Table(name: 'employer_reimbursement')]
#[ORM\Index(name: 'IDX_EMPLOYER_REIMBURSEMENT_PERSON_YEAR', columns: ['person_id', 'year'])]
class EmployerReimbursement
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36)] private string $id;
    #[ORM\Column(name: 'person_id', type: Types::STRING, length: 36)] private string $personId;
    #[ORM\Column(type: Types::STRING, length: 255)] private string $employer;
    #[ORM\Column(type: Types::INTEGER)] private int $year;
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $date;
    #[ORM\Column(type: Types::INTEGER)] private int $amountCents;
    #[ORM\Column(type: Types::STRING, length: 50)] private string $category;
    #[ORM\Column(name: 'to_reintegrate', type: Types::BOOLEAN)] private bool $toReintegrate;
    #[ORM\Column(name: 'expense_id', type: Types::STRING, length: 36, nullable: true)] private ?string $expenseId;
    public function __construct(string $id, string $personId, string $employer, int $year, int $amountCents, string $category, bool $toReintegrate, ?\DateTimeImmutable $date = null, ?string $expenseId = null)
    {
        if ('' === trim($id) || '' === trim($personId) || '' === trim($employer) || '' === trim($category)) {
            throw new \InvalidArgumentException('Reimbursement required fields are missing.');
        }
        if (null !== $date && (int) $date->format('Y') !== $year) {
            throw new \InvalidArgumentException('Reimbursement date must be within its year.');
        }
        if ($amountCents < 0) {
            throw new \InvalidArgumentException('Reimbursement amount cannot be negative.');
        }
        $this->id = $id;
        $this->personId = $personId;
        $this->employer = $employer;
        $this->year = $year;
        $this->amountCents = $amountCents;
        $this->category = $category;
        $this->toReintegrate = $toReintegrate;
        $this->date = $date;
        $this->expenseId = $expenseId;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function personId(): string
    {
        return $this->personId;
    }

    public function employer(): string
    {
        return $this->employer;
    }

    public function year(): int
    {
        return $this->year;
    }

    public function date(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function amountCents(): int
    {
        return $this->amountCents;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function toReintegrate(): bool
    {
        return $this->toReintegrate;
    }

    public function expenseId(): ?string
    {
        return $this->expenseId;
    }
}
