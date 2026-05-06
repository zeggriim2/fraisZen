<?php

declare(strict_types=1);

namespace App\Expense\Infrastructure\Persistence;

use App\Expense\Domain\Entity\BaremeKilometrique;
use App\Expense\Domain\Repository\BaremeKilometriqueRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineBaremeKilometriqueRepository implements BaremeKilometriqueRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findByYear(int $year): ?BaremeKilometrique
    {
        return $this->em->find(BaremeKilometrique::class, $year);
    }

    public function findAll(): array
    {
        return $this->em->getRepository(BaremeKilometrique::class)
            ->findBy([], ['year' => 'DESC']);
    }

    public function save(BaremeKilometrique $bareme): void
    {
        $this->em->persist($bareme);
        $this->em->flush();
    }
}
