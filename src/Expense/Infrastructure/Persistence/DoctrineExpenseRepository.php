<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Persistence;

use App\Expense\Domain\Entity\Expense;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\ValueObject\ExpenseId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineExpenseRepository implements ExpenseRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public function save(Expense $expense): void
    {
        $this->em->persist($expense);
        $this->em->flush();
    }

    public function delete(Expense $expense): void
    {
        $this->em->remove($expense);
        $this->em->flush();
    }

    public function findById(ExpenseId $id): ?Expense
    {
        return $this->em->find(Expense::class, $id->value());
    }

    public function findByPersonAndPeriod(string $personId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->em->createQueryBuilder()
            ->select('e')->from(Expense::class, 'e')
            ->where('e.personId = :personId')
            ->andWhere('e.date >= :from')
            ->andWhere('e.date <= :to')
            ->orderBy('e.date', 'ASC')
            ->setParameter('personId', $personId)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()->getResult();
    }

    public function findByPersonAndYear(string $personId, int $year): array
    {
        return $this->findByPersonAndPeriod(
            $personId,
            new \DateTimeImmutable("$year-01-01"),
            new \DateTimeImmutable("$year-12-31"),
        );
    }

    public function findByPeriod(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->em->createQueryBuilder()
            ->select('e')->from(Expense::class, 'e')
            ->where('e.date >= :from')
            ->andWhere('e.date <= :to')
            ->orderBy('e.date', 'ASC')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()->getResult();
    }
}
