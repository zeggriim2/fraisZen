<?php

declare(strict_types=1);

namespace App\Person\Domain\Repository;

use App\Person\Domain\Entity\Person;
use App\Person\Domain\ValueObject\PersonId;

interface PersonRepositoryInterface
{
    public function save(Person $person): void;

    public function delete(Person $person): void;

    public function findById(PersonId $id): ?Person;

    /** @return Person[] */
    public function findAll(): array;
}
