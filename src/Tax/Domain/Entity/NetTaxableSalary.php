<?php

declare(strict_types=1);

namespace App\Tax\Domain\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** @psalm-suppress PossiblyUnusedMethod */
#[ORM\Entity]
#[ORM\Table(name: 'net_taxable_salary')]
#[ORM\Index(name: 'IDX_NET_TAXABLE_SALARY_PERSON_YEAR', columns: ['person_id', 'year'])]
class NetTaxableSalary
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 36)]
    private string $id;

    #[ORM\Column(name: 'person_id', type: Types::STRING, length: 36)]
    private string $personId;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $employer;

    #[ORM\Column(type: Types::INTEGER)]
    private int $year;

    #[ORM\Column(name: 'period_start', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $periodStart;

    #[ORM\Column(name: 'period_end', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $periodEnd;

    #[ORM\Column(name: 'amount_cents', type: Types::INTEGER)]
    private int $amountCents;

    public function __construct(string $id, string $personId, string $employer, int $year, \DateTimeImmutable $periodStart, \DateTimeImmutable $periodEnd, int $amountCents)
    {
        if ('' === trim($id) || '' === trim($personId) || '' === trim($employer)) {
            throw new \InvalidArgumentException('Salary identity and employer are required.');
        }
        if ($periodStart > $periodEnd || (int) $periodStart->format('Y') !== $year || (int) $periodEnd->format('Y') !== $year) {
            throw new \InvalidArgumentException('Salary period must be within its year.');
        }
        if ($amountCents < 0) {
            throw new \InvalidArgumentException('Salary amount cannot be negative.');
        }
        $this->id = $id;
        $this->personId = $personId;
        $this->employer = $employer;
        $this->year = $year;
        $this->periodStart = $periodStart;
        $this->periodEnd = $periodEnd;
        $this->amountCents = $amountCents;
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

    public function periodStart(): \DateTimeImmutable
    {
        return $this->periodStart;
    }

    public function periodEnd(): \DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function amountCents(): int
    {
        return $this->amountCents;
    }
}
