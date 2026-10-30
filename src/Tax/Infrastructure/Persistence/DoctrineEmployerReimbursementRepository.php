<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\Persistence;

use App\Tax\Domain\Entity\EmployerReimbursement;
use App\Tax\Domain\Repository\EmployerReimbursementRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

/** @psalm-suppress UnusedClass */
final class DoctrineEmployerReimbursementRepository implements EmployerReimbursementRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(EmployerReimbursement $reimbursement): void
    {
        $this->em->persist($reimbursement);
        $this->em->flush();
    }

    public function remove(EmployerReimbursement $reimbursement): void
    {
        $this->em->remove($reimbursement);
        $this->em->flush();
    }

    public function findById(string $id): ?EmployerReimbursement
    {
        return $this->em->find(EmployerReimbursement::class, $id);
    }

    public function findByPersonAndYear(string $personId, int $year): array
    {
        return $this->em->getRepository(EmployerReimbursement::class)->findBy(['personId' => $personId, 'year' => $year], ['date' => 'ASC', 'id' => 'ASC']);
    }
}
