<?php

declare(strict_types=1);

namespace App\Person\Infrastructure\Http\Person;

use App\Auth\Domain\Entity\User;
use App\Person\Application\Query\GetAllPersons\GetAllPersonsQuery;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('', name: 'list', methods: [Request::METHOD_GET])]
class ListController extends AbstractController
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        return $this->json($this->queryBus->ask(new GetAllPersonsQuery($this->userId())));
    }

    private function userId(): string
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user->id()->value();
    }
}
