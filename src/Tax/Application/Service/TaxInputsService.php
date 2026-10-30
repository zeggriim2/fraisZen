<?php

declare(strict_types=1);

namespace App\Tax\Application\Service;

use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\Tax\Application\Exception\InvalidTaxInputException;
use App\Tax\Application\Exception\TaxInputNotFoundException;
use App\Tax\Domain\Entity\EmployerReimbursement;
use App\Tax\Domain\Entity\NetTaxableSalary;
use App\Tax\Domain\Repository\EmployerReimbursementRepositoryInterface;
use App\Tax\Domain\Repository\NetTaxableSalaryRepositoryInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Inputs of the forfait / real expenses comparison that the person enters herself: net taxable
 * salaries and employer reimbursements (with the explicit reintegration indicator).
 *
 * Every operation is scoped to ONE person: an identifier belonging to another person is reported
 * as not found, so one household member can never read or modify another member's inputs through
 * this service. The caller (controller) has already checked that the person belongs to the user.
 *
 * @psalm-type SalaryView = array{id: string, employer: string, year: int, periodStart: string, periodEnd: string, amountCents: int}
 * @psalm-type ReimbursementView = array{id: string, employer: string, year: int, date: string|null, amountCents: int, category: string, toReintegrate: bool|null, expenseId: string|null, linkedExpenseFound: bool|null}
 * @psalm-type TaxInputsView = array{personId: string, year: int, salaries: list<SalaryView>, reimbursements: list<ReimbursementView>, categories: list<string>}
 */
final readonly class TaxInputsService
{
    public const CATEGORIES = ['transport', 'meal', 'mileage', 'remote_work', 'other'];

    /** @psalm-suppress PossiblyUnusedMethod Autowired by the Symfony container (TaxInputsController). */
    public function __construct(
        private NetTaxableSalaryRepositoryInterface $salaries,
        private EmployerReimbursementRepositoryInterface $reimbursements,
        private ExpenseRepositoryInterface $expenses,
    ) {
    }

    /** @return TaxInputsView */
    public function list(string $personId, int $year): array
    {
        self::assertYear($year);
        $salaries = array_values(array_filter(
            $this->salaries->findByPersonAndYear($personId, $year),
            static fn (NetTaxableSalary $s): bool => $s->personId() === $personId && $s->year() === $year,
        ));
        $reimbursements = array_values(array_filter(
            $this->reimbursements->findByPersonAndYear($personId, $year),
            static fn (EmployerReimbursement $r): bool => $r->personId() === $personId && $r->year() === $year,
        ));

        return [
            'personId' => $personId,
            'year' => $year,
            'salaries' => array_map(self::salaryView(...), $salaries),
            'reimbursements' => array_map(fn (EmployerReimbursement $r): array => $this->reimbursementView($r), $reimbursements),
            'categories' => self::CATEGORIES,
        ];
    }

    /** @return SalaryView */
    public function addSalary(string $personId, int $year, string $employer, string $periodStart, string $periodEnd, int $amountCents): array
    {
        self::assertYear($year);
        $start = self::date($periodStart, 'periodStart');
        $end = self::date($periodEnd, 'periodEnd');
        if ('' === trim($employer)) {
            throw new InvalidTaxInputException('The employer is required.');
        }
        if ($start > $end || (int) $start->format('Y') !== $year || (int) $end->format('Y') !== $year) {
            throw new InvalidTaxInputException(sprintf('The salary period must be within year %d.', $year));
        }
        if ($amountCents < 0) {
            throw new InvalidTaxInputException('The net taxable salary cannot be negative.');
        }

        $salary = new NetTaxableSalary(Uuid::v4()->toRfc4122(), $personId, trim($employer), $year, $start, $end, $amountCents);
        $this->salaries->save($salary);

        return self::salaryView($salary);
    }

    public function removeSalary(string $personId, string $salaryId): void
    {
        $salary = $this->salaries->findById($salaryId);
        if (null === $salary || $salary->personId() !== $personId) {
            throw TaxInputNotFoundException::withId('Net taxable salary', $salaryId);
        }
        $this->salaries->remove($salary);
    }

    /** @return ReimbursementView */
    public function addReimbursement(string $personId, int $year, string $employer, int $amountCents, string $category, ?bool $toReintegrate, ?string $date): array
    {
        self::assertYear($year);
        if ('' === trim($employer)) {
            throw new InvalidTaxInputException('The employer is required.');
        }
        if (!\in_array($category, self::CATEGORIES, true)) {
            throw new InvalidTaxInputException('Unknown reimbursement category.');
        }
        if ($amountCents < 0) {
            throw new InvalidTaxInputException('The reimbursement amount cannot be negative.');
        }
        $day = null === $date || '' === $date ? null : self::date($date, 'date');
        if (null !== $day && (int) $day->format('Y') !== $year) {
            throw new InvalidTaxInputException(sprintf('The reimbursement date must be within year %d.', $year));
        }

        $reimbursement = new EmployerReimbursement(Uuid::v4()->toRfc4122(), $personId, trim($employer), $year, $amountCents, $category, $toReintegrate, $day);
        $this->reimbursements->save($reimbursement);

        return $this->reimbursementView($reimbursement);
    }

    /** @return ReimbursementView */
    public function indicateReintegration(string $personId, string $reimbursementId, ?bool $toReintegrate): array
    {
        $reimbursement = $this->ownedReimbursement($personId, $reimbursementId);
        $reimbursement->indicateReintegration($toReintegrate);
        $this->reimbursements->save($reimbursement);

        return $this->reimbursementView($reimbursement);
    }

    /** @return ReimbursementView */
    public function unlinkExpense(string $personId, string $reimbursementId): array
    {
        $reimbursement = $this->ownedReimbursement($personId, $reimbursementId);
        $reimbursement->unlinkExpense();
        $this->reimbursements->save($reimbursement);

        return $this->reimbursementView($reimbursement);
    }

    public function removeReimbursement(string $personId, string $reimbursementId): void
    {
        $this->reimbursements->remove($this->ownedReimbursement($personId, $reimbursementId));
    }

    private function ownedReimbursement(string $personId, string $reimbursementId): EmployerReimbursement
    {
        $reimbursement = $this->reimbursements->findById($reimbursementId);
        if (null === $reimbursement || $reimbursement->personId() !== $personId) {
            throw TaxInputNotFoundException::withId('Employer reimbursement', $reimbursementId);
        }

        return $reimbursement;
    }

    /** @return SalaryView */
    private static function salaryView(NetTaxableSalary $salary): array
    {
        return [
            'id' => $salary->id(),
            'employer' => $salary->employer(),
            'year' => $salary->year(),
            'periodStart' => $salary->periodStart()->format('Y-m-d'),
            'periodEnd' => $salary->periodEnd()->format('Y-m-d'),
            'amountCents' => $salary->amountCents(),
        ];
    }

    /** @return ReimbursementView */
    private function reimbursementView(EmployerReimbursement $reimbursement): array
    {
        return [
            'id' => $reimbursement->id(),
            'employer' => $reimbursement->employer(),
            'year' => $reimbursement->year(),
            'date' => $reimbursement->date()?->format('Y-m-d'),
            'amountCents' => $reimbursement->amountCents(),
            'category' => $reimbursement->category(),
            'toReintegrate' => $reimbursement->toReintegrate(),
            'expenseId' => $reimbursement->expenseId(),
            'linkedExpenseFound' => $this->linkedExpenseFound($reimbursement),
        ];
    }

    /** Same rule as the comparison engine: the linked expense must be an expense of this person and year. */
    private function linkedExpenseFound(EmployerReimbursement $reimbursement): ?bool
    {
        $expenseId = $reimbursement->expenseId();
        if (null === $expenseId) {
            return null;
        }
        try {
            $expense = $this->expenses->findById(ExpenseId::fromString($expenseId));
        } catch (\InvalidArgumentException) {
            return false;
        }

        return null !== $expense
            && $expense->personId() === $reimbursement->personId()
            && (int) $expense->date()->format('Y') === $reimbursement->year();
    }

    private static function assertYear(int $year): void
    {
        if ($year < 1900 || $year > 2999) {
            throw new InvalidTaxInputException('Invalid year.');
        }
    }

    private static function date(string $value, string $field): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw new InvalidTaxInputException(sprintf('Invalid date for "%s" (expected YYYY-MM-DD).', $field));
        }

        return $date;
    }
}
