<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Http\AdminUser;

use App\Admin\Application\Query\GetUserList\GetUserListQuery;
use App\Admin\Infrastructure\Http\AbstractAdminController;
use App\SharedKernel\Application\Bus\QueryBusInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/users', name: 'list', methods: [Request::METHOD_GET])]
class ListController extends AbstractAdminController
{
    public function __construct(private readonly QueryBusInterface $queryBus)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        return $this->json($this->queryBus->ask(new GetUserListQuery(
            search: (string) $request->query->get('search', ''),
            status: (string) $request->query->get('status', ''),
            page: max(1, (int) $request->query->get('page', 1)),
        )));
    }
}
