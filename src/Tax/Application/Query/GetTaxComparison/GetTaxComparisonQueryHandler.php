<?php

declare(strict_types=1);

namespace App\Tax\Application\Query\GetTaxComparison;

use App\Admin\Domain\Repository\FiscalConfigRepositoryInterface;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\Service\KilometricAllowanceCalculator;
use App\SharedKernel\Application\Bus\QueryHandlerInterface;
use App\Tax\Application\Presenter\TaxComparisonPresenter;
use App\Tax\Application\Service\TaxComparisonCalculator;
use App\Tax\Domain\Entity\TaxYearClosure;
use App\Tax\Domain\Repository\EmployerReimbursementRepositoryInterface;
use App\Tax\Domain\Repository\NetTaxableSalaryRepositoryInterface;
use App\Tax\Domain\Repository\TaxYearClosureRepositoryInterface;
use App\Tax\Domain\Service\TaxComparisonService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Loads the inputs of ONE person and ONE year, runs the comparison engine and returns
 * the display-ready payload. Nothing is ever consolidated across persons.
 *
 * Open year: the result is recomputed from the current data on every read (nothing is cached,
 * so any modification invalidates the previous result). Closed year: the immutable snapshot of the
 * closure in force is returned with the status "closed"; the current calculation is only used to
 * report whether data or rules changed since the closing.
 *
 * @psalm-import-type Presented from TaxComparisonPresenter
 */
#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetTaxComparisonQueryHandler implements QueryHandlerInterface
{
    private TaxComparisonCalculator $calculator;

    public function __construct(
        NetTaxableSalaryRepositoryInterface $salaries,
        EmployerReimbursementRepositoryInterface $reimbursements,
        ExpenseRepositoryInterface $expenses,
        FiscalConfigRepositoryInterface $fiscalConfigs,
        KilometricAllowanceCalculator $kilometricCalculator,
        TaxComparisonService $service = new TaxComparisonService(),
        private TaxComparisonPresenter $presenter = new TaxComparisonPresenter(),
        private ?TaxYearClosureRepositoryInterface $closures = null,
    ) {
        $this->calculator = new TaxComparisonCalculator($salaries, $reimbursements, $expenses, $fiscalConfigs, $kilometricCalculator, $service);
    }

    /** @return Presented */
    public function __invoke(GetTaxComparisonQuery $query): array
    {
        $current = $this->calculator->compute($query->personId, $query->year);

        $history = array_values(array_filter(
            $this->closures?->findHistory($query->personId, $query->year) ?? [],
            static fn (TaxYearClosure $c): bool => $c->personId() === $query->personId && $c->year() === $query->year,
        ));
        $active = null;
        foreach ($history as $closure) {
            if ($closure->isClosed() && (null === $active || $closure->version() > $active->version())) {
                $active = $closure;
            }
        }

        return null === $active
            ? $this->presenter->presentOpen($current, $query->today, $history)
            : $this->presenter->presentClosed($active, $current, $query->today, $history);
    }
}
