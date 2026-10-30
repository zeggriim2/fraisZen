<?php

declare(strict_types=1);

namespace App\Admin\Application\Command\UpsertFiscalConfig;

use App\Admin\Application\Exception\InvalidFiscalConfigException;
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
            // Domain rules are checked before any mutation so a rejected request leaves the year untouched.
            $this->applyTaxComparisonRules($config, $command);
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
            $this->applyTaxComparisonRules($config, $command);
        }

        $this->repository->save($config);
    }

    private function applyTaxComparisonRules(FiscalConfig $config, UpsertFiscalConfigCommand $command): void
    {
        if (!$command->hasTaxComparisonFields()) {
            return;
        }

        $missing = $command->missingTaxComparisonFields();
        if ([] !== $missing) {
            throw new InvalidFiscalConfigException(sprintf('The six tax comparison parameters must be provided together; missing: %s.', implode(', ', $missing)));
        }
        \assert(null !== $command->forfaitRatePercent && null !== $command->forfaitMinimumCents && null !== $command->forfaitMaximumCents);
        \assert(null !== $command->version && null !== $command->source && null !== $command->effectiveFrom);

        try {
            $config->defineTaxComparisonRules(
                $command->forfaitRatePercent,
                $command->forfaitMinimumCents,
                $command->forfaitMaximumCents,
                $command->version,
                $command->source,
                self::date($command->effectiveFrom),
            );
        } catch (\InvalidArgumentException $e) {
            throw new InvalidFiscalConfigException($e->getMessage(), previous: $e);
        }
    }

    private static function date(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw new InvalidFiscalConfigException(sprintf('Invalid effective date "%s": expected format YYYY-MM-DD.', $value));
        }

        return $date;
    }
}
