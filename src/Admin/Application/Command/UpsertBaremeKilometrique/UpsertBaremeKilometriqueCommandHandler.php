<?php

declare(strict_types=1);

namespace App\Admin\Application\Command\UpsertBaremeKilometrique;

use App\Expense\Domain\Entity\BaremeKilometrique;
use App\Expense\Domain\Repository\BaremeKilometriqueRepositoryInterface;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpsertBaremeKilometriqueCommandHandler implements CommandHandlerInterface
{
    public function __construct(private BaremeKilometriqueRepositoryInterface $repository)
    {
    }

    public function __invoke(UpsertBaremeKilometriqueCommand $command): void
    {
        $bareme = $this->repository->findByYear($command->year)
            ?? new BaremeKilometrique($command->year, $command->rates);

        $bareme->setRates($command->rates);
        $this->repository->save($bareme);
    }
}