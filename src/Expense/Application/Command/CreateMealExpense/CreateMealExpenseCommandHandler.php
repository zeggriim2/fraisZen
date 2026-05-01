<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\CreateMealExpense;

use App\Admin\Domain\Repository\FiscalConfigRepositoryInterface;
use App\Expense\Domain\Entity\MealExpense;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateMealExpenseCommandHandler implements CommandHandlerInterface
{
    private const FALLBACK_HOME_MEAL_VALUE = 5.35;

    public function __construct(
        private ExpenseRepositoryInterface $repository,
        private FiscalConfigRepositoryInterface $fiscalConfigRepository,
    ) {
    }

    public function __invoke(CreateMealExpenseCommand $command): string
    {
        $year = (int) (new \DateTimeImmutable($command->date))->format('Y');
        $config = $this->fiscalConfigRepository->findByYear($year);
        $rate = $config?->homeMealValue() ?? self::FALLBACK_HOME_MEAL_VALUE;

        $id = ExpenseId::generate();
        $this->repository->save(new MealExpense(
            id: $id,
            personId: $command->personId,
            date: new \DateTimeImmutable($command->date),
            description: $command->description,
            mealAmount: $command->mealAmount,
            homeMealValue: $rate,
            employerTicketContribution: $command->employerTicketContribution,
            withoutReceipt: $command->withoutReceipt,
        ));

        return $id->value();
    }
}
