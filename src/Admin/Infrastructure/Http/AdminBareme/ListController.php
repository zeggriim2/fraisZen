<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminBareme;

use App\Admin\Application\Query\ListBaremeKilometrique\ListBaremeKilometriqueQuery;
use App\Admin\Infrastructure\Http\AbstractAdminController;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/bareme-kilometrique', name: 'list', methods: [Request::METHOD_GET])]
class ListController extends AbstractAdminController
{
    public function __construct(private readonly QueryBusInterface $queryBus)
    {
    }

    public function __invoke(): JsonResponse
    {
        return $this->json($this->queryBus->ask(new ListBaremeKilometriqueQuery()));
    }
}
