<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\Persistence;

use App\Tax\Domain\Entity\NetTaxableSalary;
use App\Tax\Domain\Repository\NetTaxableSalaryRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

/** @psalm-suppress UnusedClass */
final class DoctrineNetTaxableSalaryRepository implements NetTaxableSalaryRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(NetTaxableSalary $salary): void
    {
        $this->em->persist($salary);
        $this->em->flush();
    }

    public function findByPersonAndYear(string $personId, int $year): array
    {
        return $this->em->getRepository(NetTaxableSalary::class)->findBy(['personId' => $personId, 'year' => $year], ['periodStart' => 'ASC']);
    }
}
