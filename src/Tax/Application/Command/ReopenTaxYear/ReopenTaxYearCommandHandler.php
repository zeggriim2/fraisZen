<?php

declare(strict_types=1);

namespace App\Tax\Application\Command\ReopenTaxYear;

use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use App\Tax\Domain\Exception\TaxYearClosureConflictException;
use App\Tax\Domain\Repository\TaxYearClosureRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Explicit reopening: the closure in force is marked as reopened (who, when) and kept, so the
 * comparison is computed again from the current data until the year is closed again (new version).
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class ReopenTaxYearCommandHandler implements CommandHandlerInterface
{
    public function __construct(private TaxYearClosureRepositoryInterface $closures)
    {
    }

    /** @return array{personId: string, year: int, version: int, state: 'closed'|'reopened', closedAt: string, reopenedAt: string|null} */
    public function __invoke(ReopenTaxYearCommand $command): array
    {
        $closure = $this->closures->findActive($command->personId, $command->year);
        if (null === $closure || $closure->personId() !== $command->personId || $closure->year() !== $command->year) {
            throw TaxYearClosureConflictException::notClosed($command->year);
        }
        if ($closure->version() !== $command->version) {
            throw TaxYearClosureConflictException::versionMismatch($command->year, $command->version, $closure->version());
        }

        $closure->reopen($command->userId, $command->reopenedAt);
        $this->closures->save($closure);

        return $closure->summary();
    }
}
