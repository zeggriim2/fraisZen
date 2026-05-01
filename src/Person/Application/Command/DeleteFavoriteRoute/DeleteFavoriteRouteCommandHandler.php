<?php

declare(strict_types=1);

namespace App\Person\Application\Command\DeleteFavoriteRoute;

use App\Person\Domain\Exception\FavoriteRouteNotFoundException;
use App\Person\Domain\Repository\FavoriteRouteRepositoryInterface;
use App\Person\Domain\ValueObject\FavoriteRouteId;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DeleteFavoriteRouteCommandHandler implements CommandHandlerInterface
{
    public function __construct(private FavoriteRouteRepositoryInterface $repository)
    {
    }

    public function __invoke(DeleteFavoriteRouteCommand $command): void
    {
        $route = $this->repository->findById(FavoriteRouteId::fromString($command->id))
            ?? throw FavoriteRouteNotFoundException::withId($command->id);

        $this->repository->delete($route);
    }
}
