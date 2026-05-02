<?php

declare(strict_types=1);

namespace App\Person\Infrastructure\Persistence;

use App\Person\Domain\Entity\FavoriteRoute;
use App\Person\Domain\Repository\FavoriteRouteRepositoryInterface;
use App\Person\Domain\ValueObject\FavoriteRouteId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineFavoriteRouteRepository implements FavoriteRouteRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    #[\Override]
    public function save(FavoriteRoute $route): void
    {
        $this->em->persist($route);
        $this->em->flush();
    }

    #[\Override]
    public function delete(FavoriteRoute $route): void
    {
        $this->em->remove($route);
        $this->em->flush();
    }

    #[\Override]
    public function findById(FavoriteRouteId $id): ?FavoriteRoute
    {
        return $this->em->find(FavoriteRoute::class, $id->value());
    }

    #[\Override]
    public function findByPersonId(string $personId): array
    {
        return $this->em->createQueryBuilder()
            ->select('r')->from(FavoriteRoute::class, 'r')
            ->where('r.personId = :personId')->setParameter('personId', $personId)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()->getResult();
    }
}
