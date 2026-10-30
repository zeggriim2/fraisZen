<?php

declare(strict_types=1);

namespace App\Tax\Infrastructure\Persistence;

use App\Tax\Domain\Entity\TaxYearClosure;
use App\Tax\Domain\Exception\TaxYearClosureConflictException;
use App\Tax\Domain\Repository\TaxYearClosureRepositoryInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;

/** @psalm-suppress UnusedClass */
final class DoctrineTaxYearClosureRepository implements TaxYearClosureRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function add(TaxYearClosure $closure): void
    {
        try {
            $this->em->persist($closure);
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // Another request closed the same person, year and version first: its snapshot is kept.
            throw TaxYearClosureConflictException::alreadyClosed($closure->year(), $closure->version());
        }
    }

    public function save(TaxYearClosure $closure): void
    {
        try {
            $this->em->flush();
        } catch (OptimisticLockException) {
            throw TaxYearClosureConflictException::concurrentModification();
        }
    }

    public function findActive(string $personId, int $year): ?TaxYearClosure
    {
        return $this->em->getRepository(TaxYearClosure::class)->findOneBy(
            ['personId' => $personId, 'year' => $year, 'reopenedAt' => null],
            ['version' => 'DESC'],
        );
    }

    public function findHistory(string $personId, int $year): array
    {
        return $this->em->getRepository(TaxYearClosure::class)->findBy(['personId' => $personId, 'year' => $year], ['version' => 'ASC']);
    }
}
