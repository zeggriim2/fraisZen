<?php

declare(strict_types=1);

namespace App\Person\Infrastructure\Persistence;

use App\Person\Domain\Entity\Person;
use App\Person\Domain\Repository\PersonRepositoryInterface;
use App\Person\Domain\ValueObject\PersonId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePersonRepository implements PersonRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function save(Person $person): void
    {
        $this->em->persist($person);
        $this->em->flush();
    }

    public function delete(Person $person): void
    {
        $this->em->remove($person);
        $this->em->flush();
    }

    public function findById(PersonId $id): ?Person
    {
        return $this->em->find(Person::class, $id->value());
    }

    public function findAllByUserId(string $userId): array
    {
        return $this->em->createQueryBuilder()
            ->select('p')->from(Person::class, 'p')
            ->where('p.userId = :userId')->setParameter('userId', $userId)
            ->orderBy('p.favorite', 'DESC')->addOrderBy('p.lastName', 'ASC')->addOrderBy('p.firstName', 'ASC')
            ->getQuery()->getResult();
    }
}
