<?php

declare(strict_types=1);

namespace App\Person\Application\Query\GetPersonById;

use App\Person\Domain\Exception\PersonNotFoundException;
use App\Person\Domain\Repository\PersonRepositoryInterface;
use App\Person\Domain\ValueObject\PersonId;
use App\SharedKernel\Application\Bus\QueryHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetPersonByIdQueryHandler implements QueryHandlerInterface
{
    public function __construct(private PersonRepositoryInterface $repository)
    {
    }

    /** @return array<string, mixed> */
    public function __invoke(GetPersonByIdQuery $query): array
    {
        $person = $this->repository->findById(PersonId::fromString($query->id))
            ?? throw PersonNotFoundException::withId($query->id);

        return $person->toArray();
    }
}
