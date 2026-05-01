<?php

declare(strict_types=1);

namespace App\Person\Application\Command\CreateFavoriteRoute;

use App\Person\Domain\Entity\FavoriteRoute;
use App\Person\Domain\Repository\FavoriteRouteRepositoryInterface;
use App\Person\Domain\ValueObject\FavoriteRouteId;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateFavoriteRouteCommandHandler implements CommandHandlerInterface
{
    public function __construct(private FavoriteRouteRepositoryInterface $repository)
    {
    }

    public function __invoke(CreateFavoriteRouteCommand $command): string
    {
        $id = FavoriteRouteId::generate();
        $this->repository->save(new FavoriteRoute(
            $id,
            $command->personId,
            $command->name,
            $command->departure,
            $command->arrival,
            $command->vehicleType,
            $command->vehiclePower,
            $command->isElectric,
            $command->roundTrip,
        ));

        return $id->value();
    }
}
