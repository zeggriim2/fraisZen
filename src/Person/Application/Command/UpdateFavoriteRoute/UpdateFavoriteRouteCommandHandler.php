<?php

declare(strict_types=1);

namespace App\Person\Application\Command\UpdateFavoriteRoute;

use App\Person\Domain\Exception\FavoriteRouteNotFoundException;
use App\Person\Domain\Repository\FavoriteRouteRepositoryInterface;
use App\Person\Domain\ValueObject\FavoriteRouteId;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpdateFavoriteRouteCommandHandler implements CommandHandlerInterface
{
    public function __construct(private FavoriteRouteRepositoryInterface $repository)
    {
    }

    public function __invoke(UpdateFavoriteRouteCommand $command): void
    {
        $route = $this->repository->findById(FavoriteRouteId::fromString($command->id))
            ?? throw FavoriteRouteNotFoundException::withId($command->id);

        $route->update(
            $command->name,
            $command->departure,
            $command->arrival,
            $command->vehicleType,
            $command->vehiclePower,
            $command->isElectric,
            $command->roundTrip,
        );

        $this->repository->save($route);
    }
}
