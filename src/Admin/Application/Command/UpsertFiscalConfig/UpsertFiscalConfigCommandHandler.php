<?php

declare(strict_types=1);

namespace App\Admin\Application\Command\UpsertFiscalConfig;

use App\Admin\Domain\Entity\FiscalConfig;
use App\Admin\Domain\Repository\FiscalConfigRepositoryInterface;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UpsertFiscalConfigCommandHandler implements CommandHandlerInterface
{
    public function __construct(private FiscalConfigRepositoryInterface $repository)
    {
    }

    public function __invoke(UpsertFiscalConfigCommand $command): void
    {
        $config = $this->repository->findByYear($command->year);

        if ($config) {
            $config->setRemoteWorkDailyAllowance($command->remoteWorkDailyAllowance);
            if (null !== $command->homeMealValue) {
                $config->setHomeMealValue($command->homeMealValue);
            }
        } else {
            $config = new FiscalConfig(
                $command->year,
                (string) $command->remoteWorkDailyAllowance,
                null !== $command->homeMealValue ? (string) $command->homeMealValue : '5.35',
            );
        }

        $this->repository->save($config);
    }
}
