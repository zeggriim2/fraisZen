<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Persistence;

use App\Auth\Domain\Entity\User;
use App\Auth\Domain\Repository\UserRepositoryInterface;
use App\Auth\Domain\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public function save(User $user): void
    {
        $this->em->persist($user);
        $this->em->flush();
    }

    public function delete(User $user): void
    {
        $this->em->remove($user);
        $this->em->flush();
    }

    public function findById(UserId $id): ?User
    {
        return $this->em->find(User::class, $id->value());
    }

    public function findByEmail(string $email): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    public function findByStripeCustomerId(string $customerId): ?User
    {
        return $this->em->getRepository(User::class)->findOneBy(['stripeCustomerId' => $customerId]);
    }
}
