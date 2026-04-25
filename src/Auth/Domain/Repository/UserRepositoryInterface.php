<?php

declare(strict_types=1);

namespace App\Auth\Domain\Repository;

use App\Auth\Domain\Entity\User;
use App\Auth\Domain\ValueObject\UserId;

interface UserRepositoryInterface
{
    public function save(User $user): void;
    public function delete(User $user): void;
    public function findById(UserId $id): ?User;
    public function findByEmail(string $email): ?User;
    public function findByStripeCustomerId(string $customerId): ?User;

    /** @return User[] */
    public function findAll(): array;

    /** @return User[] */
    public function findByEmailLike(string $search): array;

    public function count(): int;
}
