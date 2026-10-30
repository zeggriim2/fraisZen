<?php

declare(strict_types=1);

namespace App\Tax\Application\Command\CloseTaxYear;

use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use App\Tax\Application\Service\TaxComparisonCalculator;
use App\Tax\Domain\Entity\TaxYearClosure;
use App\Tax\Domain\Exception\TaxYearClosureConflictException;
use App\Tax\Domain\Exception\TaxYearNotClosableException;
use App\Tax\Domain\Repository\TaxYearClosureRepositoryInterface;
use App\Tax\Domain\Service\TaxComparisonResult;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Closing flow: the calculation is recomputed from the current data and must be
 *  1. complete ("available"), otherwise 422;
 *  2. the very calculation the user validated (same fingerprint), otherwise 409;
 *  3. not already closed (a closed year must be reopened explicitly first), otherwise 409.
 * A new closure version then stores the immutable snapshot; a concurrent closing of the same
 * version is rejected by the repository (unique constraint) instead of overwriting the first one.
 */
#[AsMessageHandler(bus: 'command.bus')]
final readonly class CloseTaxYearCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private TaxComparisonCalculator $calculator,
        private TaxYearClosureRepositoryInterface $closures,
    ) {
    }

    /** @return array{personId: string, year: int, version: int, state: 'closed'|'reopened', closedAt: string, reopenedAt: string|null} */
    public function __invoke(CloseTaxYearCommand $command): array
    {
        $current = $this->calculator->compute($command->personId, $command->year);
        if (TaxComparisonResult::STATUS_AVAILABLE !== $current->result->status()) {
            throw TaxYearNotClosableException::becauseStatus($command->year, $current->result->status());
        }

        $history = $this->closures->findHistory($command->personId, $command->year);
        $lastVersion = 0;
        foreach ($history as $closure) {
            if ($closure->isClosed()) {
                throw TaxYearClosureConflictException::alreadyClosed($command->year, $closure->version());
            }
            $lastVersion = max($lastVersion, $closure->version());
        }
        if (!hash_equals($current->fingerprint, $command->calculationFingerprint)) {
            throw TaxYearClosureConflictException::staleCalculation($command->year);
        }

        $closure = TaxYearClosure::close(
            Uuid::v4()->toRfc4122(),
            $command->personId,
            $command->year,
            $lastVersion + 1,
            $command->userId,
            $command->closedAt,
            $current->result,
            $current->origins,
            $current->retainedData,
            $current->fingerprint,
        );
        $this->closures->add($closure);

        return $closure->summary();
    }
}
